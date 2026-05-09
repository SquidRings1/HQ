<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = AdminUser::firstOrCreate(
            ['email' => 'admin@hq.local'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('AdminDemo123!'),
            ]
        );

        $now = now();
        $userIds = [];
        foreach ([['Demo Rider', 'rider@hq.local'], ['Alice Test', 'alice@hq.local']] as [$name, $email]) {
            $userIds[] = DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('RiderDemo123!'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $events = [
            [
                'name' => 'Sunset ride - Corniche',
                'about' => 'Easy 12km ride along the Corniche, beginner friendly.',
                'address' => 'Corniche, Abu Dhabi',
                'phone' => '+971 50 000 0001',
                'date' => now()->addDays(7)->toDateString(),
                'starttime' => '17:30',
                'endtime' => '19:00',
                'capacity' => 30,
            ],
            [
                'name' => 'Hill climb - Hatta',
                'about' => 'Intermediate mountain ride, ~25km.',
                'address' => 'Hatta Trail, Dubai',
                'phone' => '+971 50 000 0002',
                'date' => now()->addDays(14)->toDateString(),
                'starttime' => '06:00',
                'endtime' => '10:00',
                'capacity' => 15,
            ],
            [
                'name' => 'City night ride',
                'about' => 'Casual urban ride after work.',
                'address' => 'Downtown, Dubai',
                'phone' => null,
                'date' => now()->addDays(2)->toDateString(),
                'starttime' => '20:00',
                'endtime' => '21:30',
                'capacity' => 0,
            ],
        ];

        foreach ($events as $e) {
            Event::firstOrCreate(
                ['name' => $e['name']],
                $e + ['created_by_admin_id' => $admin->id]
            );
        }
    }
}
