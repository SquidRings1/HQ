<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Compatibility shim for the gocyc mobile client.
 *
 * The mobile app talks to the production gocyc backend, which uses passwordless
 * phone-based auth and `{status, message, data}` response envelopes. This controller
 * exposes that contract on top of the simpler HQ schema so the mobile demo
 * (register → login → list events → join) works against api.exegide.com.
 *
 * NOT meant for production: login is passwordless (no SMS OTP verification),
 * which is acceptable for the school demo but a real backend would gate it.
 */
class MobileShimController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'FirstName' => ['required', 'string', 'max:120'],
            'LastName' => ['nullable', 'string', 'max:120'],
            'CountryCode' => ['required', 'string', 'max:8'],
            'MobileNumber' => ['required', 'regex:/^[0-9\s\-\+\(\)]+$/', 'min:6'],
            'Email' => ['nullable', 'email'],
        ]);

        $country = self::normalizeCountryCode($data['CountryCode']);
        $phone = self::normalizePhone($data['MobileNumber']);

        $existing = User::where('country_code', $country)
            ->whereIn('phone', self::phoneVariants($phone))
            ->first();
        if ($existing) {
            return response()->json(['status' => 0, 'message' => 'User already exists', 'data' => []], 422);
        }

        $providedEmail = $data['Email'] ?? null;
        $email = $providedEmail ?: "{$country}_{$phone}@hq.local";
        $firstName = $data['FirstName'] ?? '';
        $lastName = $data['LastName'] ?? '';
        $name = trim($firstName.' '.$lastName);

        $user = User::create([
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'password' => Hash::make(Str::random(40)), // never used; login is passwordless
            'country_code' => $country,
            'phone' => $phone,
            'fname' => $firstName,
            'lname' => $lastName,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'status' => 1,
            'message' => 'Registration completed successfully',
            'data' => self::userPayload($user, $token),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'MobileNumber' => ['required', 'regex:/^[0-9\s\-\+\(\)]+$/', 'min:6'],
            'CountryCode' => ['nullable', 'string', 'max:8'],
        ]);

        $phone = self::normalizePhone($data['MobileNumber']);
        $variants = self::phoneVariants($phone);
        $query = User::whereIn('phone', $variants);
        if (! empty($data['CountryCode'])) {
            $query->where('country_code', self::normalizeCountryCode($data['CountryCode']));
        }

        $user = $query->orderByDesc('id')->first();
        if (! $user) {
            return response()->json(['status' => 0, 'message' => 'User does not exist'], 422);
        }

        // Rotate token: revoke old, mint new.
        $user->tokens()->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'status' => 1,
            'message' => 'User logged in successfully',
            'data' => self::userPayload($user, $token),
        ]);
    }

    public function validateUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'CountryCode' => ['required', 'string', 'max:8'],
            'MobileNumber' => ['required', 'regex:/^[0-9\s\-\+\(\)]+$/'],
            'Email' => ['nullable', 'email'],
            'Mode' => ['nullable', 'in:signup,login'],
        ]);

        $country = self::normalizeCountryCode($data['CountryCode']);
        $phone = self::normalizePhone($data['MobileNumber']);
        $email = trim((string) ($data['Email'] ?? ''));
        $mode = $data['Mode'] ?? ($email !== '' ? 'signup' : 'legacy-login');

        $phoneExists = User::where('country_code', $country)
            ->whereIn('phone', self::phoneVariants($phone))
            ->exists();

        if ($mode === 'login') {
            return $phoneExists
                ? response()->json(['status' => 1, 'message' => 'Phone number is registered', 'data' => ['PhoneExists' => true]])
                : response()->json(['status' => 0, 'message' => 'No account found for this phone number', 'data' => ['PhoneExists' => false]]);
        }

        // signup / legacy-login: phone must NOT exist
        if ($phoneExists) {
            return response()->json([
                'status' => 0,
                'message' => 'This phone number is already registered',
                'data' => ['PhoneExists' => true],
            ]);
        }

        if ($email !== '') {
            $emailExists = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists();
            if ($emailExists) {
                return response()->json([
                    'status' => 0,
                    'message' => 'This email is already registered',
                    'data' => ['PhoneExists' => false, 'EmailExists' => true],
                ]);
            }
        }

        return response()->json([
            'status' => 1,
            'message' => 'Credentials are available',
            'data' => ['PhoneExists' => false, 'EmailExists' => false],
        ]);
    }

    public function getEvents(Request $request): JsonResponse
    {
        $events = Event::orderBy('date')->get()->map(fn ($e) => self::eventPayload($e));

        return response()->json(['status' => 1, 'message' => 'Events retrieved', 'data' => $events]);
    }

    public function getEventDetail(Request $request): JsonResponse
    {
        $data = $request->validate(['EventId' => ['required', 'integer']]);

        $event = Event::find($data['EventId']);
        if (! $event) {
            return response()->json(['status' => 0, 'message' => 'Event not found'], 404);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Event detail retrieved',
            'data' => self::eventPayload($event),
        ]);
    }

    public function joinEvent(Request $request): JsonResponse
    {
        $data = $request->validate(['EventId' => ['required', 'integer']]);

        $user = $request->user();
        $event = Event::find($data['EventId']);
        if (! $event) {
            return response()->json(['status' => 0, 'message' => 'Event not found'], 404);
        }

        DB::table('event_participants')->updateOrInsert(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]
        );

        return response()->json([
            'status' => 1,
            'message' => 'Joined event',
            'data' => ['EventId' => $event->id],
        ]);
    }

    public function myJoinedEvents(Request $request): JsonResponse
    {
        $events = $request->user()->joinedEvents()->orderBy('date')->get()
            ->map(fn ($e) => self::eventPayload($e));

        return response()->json(['status' => 1, 'message' => 'Joined events retrieved', 'data' => $events]);
    }

    private static function userPayload(User $user, string $token): array
    {
        return [
            'Id' => $user->id,
            'Token' => $token,
            'FirstName' => $user->fname ?? '',
            'LastName' => $user->lname ?? '',
            'UserName' => $user->name ?? '',
            'Email' => $user->email ?? '',
            'CountryCode' => $user->country_code ?? '',
            'MobileNumber' => $user->phone ?? '',
            'IsVerify' => 'TRUE',
            'IsActive' => 'TRUE',
            'PaymentReady' => 'FALSE',
        ];
    }

    private static function eventPayload(Event $event): array
    {
        return [
            'EventId' => $event->id,
            'EventName' => $event->name,
            'EventAbout' => $event->about,
            'EventAddress' => $event->address,
            'EventDate' => $event->date?->toDateString() ?? (string) $event->date,
            'EventStartTime' => $event->starttime,
            'EventEndTime' => $event->endtime,
            'EventCapacity' => (int) $event->capacity,
        ];
    }

    private static function normalizeCountryCode(?string $cc): string
    {
        return ltrim((string) $cc, '+');
    }

    private static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * Return phone variants we'll accept for lookup: as-stored, leading-zero
     * stripped, and re-prefixed with 0. Matches the production gocyc backend.
     */
    private static function phoneVariants(string $phone): array
    {
        $stripped = ltrim($phone, '0');

        return array_values(array_unique(array_filter([$phone, $stripped, '0'.$stripped])));
    }
}
