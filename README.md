
Built by https://www.blackbox.ai

---

```markdown
# CORS API + Authentication System

## Project Overview
This project is a backend API that implements CORS and authentication for a system managing users, their roles, and their associated schedules and materials in an educational context. It provides endpoints for user authentication, teacher management, student management, classes, lessons, and materials.

## Installation

### Prerequisites
- PHP 7.2 or higher
- MySQL or any compatible database
- Composer (for dependency management)
- A web server (Apache, Nginx, etc.)

### Steps
1. Clone this repository:
   ```bash
   git clone https://github.com/yourusername/repository.git
   cd repository
   ```

2. Configure your database:
   - Create a database in MySQL.
   - Import any provided SQL schema or create the relevant tables as needed.

3. Install necessary PHP extensions:
   Make sure to have extensions enabled in your `php.ini` file such as `mysqli`, `json`, and `curl`.

4. Set up the environment:
   - Update the database configuration in `config/database.php` with your database credentials.

5. Install Composer dependencies (if applicable):
   ```bash
   composer install
   ```

6. Configure web server:
   - Ensure your web server points the document root to your project directory.
   - Enable URL rewriting if needed (for clean URLs).

## Usage

### API Endpoints
- **Authentication:**
  - POST `/api/auth/login`: User login endpoint.
  - POST `/api/auth/logout`: User logout endpoint.

- **User Management:**
  - GET `/api/guru`: Retrieve list of teachers.
  - POST `/api/guru`: Add a new teacher.
  - PUT `/api/guru/{id}`: Update a teacher's record.
  - DELETE `/api/guru/{id}`: Delete a teacher.

- **Schedule Management:**
  - GET `/api/jadwal`: Get all schedules.
  - POST `/api/jadwal`: Add a new schedule.
  - PUT `/api/jadwal/{id}`: Update a schedule.
  - DELETE `/api/jadwal/{id}`: Delete a schedule.

- **Materials Management:**
  - GET `/api/materi`: Get all materials.
  - POST `/api/materi`: Add a new material.
  - PUT `/api/materi/{id}`: Update a material.
  - DELETE `/api/materi/{id}`: Delete a material.

### Example Request
To log in as a user:
```bash
curl -X POST http://yourdomain.com/api/auth/login -H "Content-Type: application/json" -d '{"email": "user@example.com", "kata_sandi": "password"}'
```

## Features
- CORS support for cross-origin requests.
- Authentication using JWT for secure access control.
- Role-based access for teachers and students to restrict functionalities.
- Database support for managing users, schedules, and materials with proper relationships.

## Dependencies
This project uses PHP's built-in functions and does not have external package dependencies defined in `composer.json`. You may include additional libraries as per your requirements.

## Project Structure
```
/project-root
├── config
│   ├── cors.php      # CORS configuration
│   ├── database.php   # Database connection setup
│   └── jwt.php       # JWT handling
├── middleware
│   ├── auth.php      # Authentication middleware
│   └── validation_helper.php # Helper functions for validation
├── api                # Folder containing all API endpoint scripts
│   ├── auth.php
│   ├── guru.php
│   ├── jadwal.php
│   ├── kelas.php
│   ├── mapel.php
│   ├── materi.php
│   ├── nilai.php
│   ├── pengumpulan.php
│   └── logout.php
└── index.php         # Entry point for the API
```

## Contributing
Feel free to submit pull requests or open issues for enhancements or bug fixes. Always ensure that your changes adhere to the project's coding standards.

## License
This project is licensed under [MIT License](LICENSE).
```