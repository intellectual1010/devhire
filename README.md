# DevHire

DevHire is a custom WordPress developer hiring platform built to demonstrate full-stack WordPress development without relying on page builders or large feature plugins.

It provides separate candidate and employer experiences, job publishing and discovery, candidate profiles and resumes, applications, applicant tracking, hiring workflow tools, company profiles, saved jobs, and custom REST/AJAX functionality.

## Features

### Job Marketplace
- Developer job listings and job detail pages
- Search and filtering by keyword, skill, job type, and location
- Structured salary, experience, remote, deadline, and application data
- Job expiration handling
- Saved jobs
- Company directory and company profiles

### Candidate Portal
- Candidate registration and login
- Candidate dashboard
- Professional profile management
- Resume upload and removal
- Application submission using profile information
- Application history and status tracking
- Application detail view
- Hiring progress from New through Reviewing, Interview, Hired, or Rejected

### Employer Portal
- Employer registration and login
- Employer dashboard
- Company profile and logo management
- Create, edit, publish, unpublish, and delete jobs
- Applicant pipeline
- Applicant search, filtering, sorting, and pagination
- Candidate profile view
- Resume and contact quick actions
- Application status management
- Bulk applicant status updates
- Private employer notes
- Candidate follow-up dates
- Follow-up due/overdue indicators
- Hiring history and workflow statistics

### Hiring Workflow
- New
- Reviewing
- Interview
- Hired
- Rejected
- Status history/timeline
- Application age and status age
- Needs-attention detection
- Follow-up scheduling
- Follow-up filtering and sorting
- Context-preserving navigation between applicant lists, applications, and candidate profiles

## Technology Stack

- WordPress
- PHP
- MariaDB / MySQL
- JavaScript
- HTML5
- CSS3
- WordPress REST API
- AJAX
- Docker / Docker Compose
- Git / GitHub

The project uses a custom WordPress theme and a custom `devhire-core` plugin.

## Architecture

```text
DevHire/
├── docker-compose.yml
├── php/
│   └── uploads.ini
└── wordpress/
    └── wp-content/
        ├── themes/
        │   └── devhire/
        └── plugins/
            └── devhire-core/
                ├── devhire-core.php
                ├── assets/
                │   └── js/
                └── includes/
```

The theme is responsible primarily for presentation and templates. Business logic is kept in the custom `devhire-core` plugin.

Core plugin modules include:

- Custom post types
- Taxonomies
- Job metadata
- Company metadata
- Applications
- Saved jobs
- REST API
- Job search
- Candidate accounts and profiles
- Employer portal and hiring workflow

## WordPress Data Model

### Custom Post Types
- `job`
- `company`
- `job_application`

Applications are stored as private WordPress posts.

### Job Taxonomies
- `job_skill`
- `job_type`
- `job_location`

### Candidate Data
Candidate account/profile information is stored using WordPress users and user metadata.

### Employer Ownership
Employer-created jobs are associated with the employer account, while company ownership is validated before company-management operations.

## Local Setup

### Requirements
- Docker
- Docker Compose
- Git

### 1. Clone the repository

```bash
git clone https://github.com/intellectual1010/devhire.git
cd DevHire
```

### 2. Start the containers

```bash
docker compose up -d
```

### 3. Open WordPress

Visit:

```text
http://localhost:8080
```

Complete the initial WordPress setup if this is a fresh installation.

### 4. Activate DevHire

In WordPress Admin:

1. Activate the **DevHire** theme.
2. Activate the **DevHire Core** plugin.

### 5. Configure pages

Create the required WordPress pages and assign the DevHire shortcodes used by the candidate and employer portals.

Examples include:

```text
/candidate-register/
/candidate-login/
/candidate-dashboard/
/candidate-profile/
/candidate-application/

/employer-register/
/employer-login/
/employer-dashboard/
/employer-company-profile/
/employer-applicants/
/employer-candidate-profile/
/employer-view-application/
```

## Security

The project includes:

- WordPress nonce verification for state-changing requests
- Role-based candidate/employer access
- Employer job/application ownership checks
- Input sanitization
- Output escaping
- Safe redirect validation
- Resume file size/type/extension validation
- Private application records
- REST/AJAX permission and nonce checks where required

### Demo Limitation

Candidate resumes currently use standard WordPress Media Library URLs. This is suitable for a portfolio/demo environment, but a production recruitment platform should use authenticated downloads or private object storage for sensitive candidate documents.

## PHP Compatibility

The project includes null-safe pagination handling to avoid PHP 8.x deprecation warnings when WordPress `paginate_links()` returns no output.

## Demo

Live Demo: https://devhire-demo.ifree.page/

## What This Project Demonstrates

DevHire demonstrates practical WordPress engineering beyond content-site development, including:

- Custom plugin architecture
- Custom theme development
- WordPress authentication and roles
- Custom post types and taxonomies
- User and post metadata
- Secure form processing
- File uploads
- REST and AJAX functionality
- Role/ownership authorization
- Search and filtering
- Multi-step hiring workflows
- Responsive application UI
- Docker-based local development

## Author

**Manny Luzano**

Full Stack Developer

## License

This project is intended primarily as a portfolio and demonstration project.
