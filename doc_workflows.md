1. Infrastructure Apply (apply.yml)
Purpose: Deploys or updates the AWS infrastructure using Terraform.

Trigger: Manual (workflow_dispatch).

Environments: dev (default) or prd.

Key Actions:

Checks out the infrastructure repository using a Personal Access Token (PAT).

Configures AWS credentials.

Initializes Terraform with environment-specific backends.

Executes terraform apply with automated approval.

2. Infrastructure Destroy (destroy.yml)
Purpose: Tears down the specified environment to manage costs and resources.

Trigger: Manual (workflow_dispatch).

Security Feature: Includes a Confirmation Guard. You must explicitly type destroy-dev or destroy-prd to proceed, preventing accidental deletion.

Key Actions: Runs terraform destroy against the selected environment.

3. Drift Detection (drift-detection.yml)
Purpose: Ensures the "Code as Truth" principle by detecting changes by comparing the desired state  against the terraform backend through terraform plan --detailed-encoode

Trigger: Scheduled (Daily at 06:00 UTC) or Manual.

Mechanism: Runs terraform plan -detailed-exitcode.

Alerting: If the infrastructure has "drifted" from the code, the workflow automatically opens a GitHub Issue with logs and fails the build to alert the team.

4. Continuous Integration (ci.yml)
Purpose: Ensures code quality and functional stability for the api and admin services.

Trigger: Every Push to main and every Pull Request.

Stack: PHP 8.4, Composer.

Key Actions:

Runs Laravel Pint for code style consistency.

Executes PHPUnit tests using an in-memory SQLite database.

Utilizes a build matrix to test both services in parallel.

5. Docker Deployment (deploy.yml)
Purpose: Builds production-ready images and updates the live ECS cluster.

Trigger: Manual or version tags (v*.*.*).

Key Actions:

Preflight Check: Validates that all required AWS and ECR secrets are present before starting.

Build & Push: Uses Docker Buildx to build images, generate SBOMs (Software Bill of Materials), and push to Amazon ECR with latest and SHA tags.

ECS Rollout: Commands a force-new-deployment on the Amazon ECS services to pull the new images immediately.

Gitleaks: Scans the entire commit history for accidentally exposed API keys or passwords.
Trivy:Scans the filesystem for known security vulnerabilities (CVEs).
Hadolint: Validates Dockerfile best practices (security and optimization).
Checkov: Scans Terraform files for cloud misconfigurations (e.g., public S3 buckets).








