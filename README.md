# InfoAgent Vehicle Report API Integration (PHP)

## Overview

This project provides a PHP-based integration for the InfoAgent API, enabling users to fetch vehicle safety reports (PPSR) and PPSR certificates. It includes PHP client classes for interacting with the InfoAgent API, backend API endpoints to expose this functionality, and a simple HTML/JavaScript frontend to request reports and download certificates.

The system handles OAuth 2.0 client credentials authentication with the InfoAgent API, automatically managing access tokens.

## Features

*   Fetches vehicle safety reports (PPSR) based on:
    *   Vehicle Identification Number (VIN)
    *   Plate Number and State
    *   Chassis Number and Make
*   Retrieves PPSR certificates by their ID.
*   Handles OAuth 2.0 client credentials authentication with the InfoAgent API, including token fetching and renewal.
*   Provides a basic web form for user interaction.

## File Structure

*   **`InfoAgentAuthClient.php`**: PHP class responsible for handling OAuth 2.0 authentication with the InfoAgent API. It fetches and caches access tokens.
*   **`VehicleReportClient.php`**: PHP class that acts as a client to the InfoAgent vehicle report and PPSR certificate API endpoints. It uses `InfoAgentAuthClient` for authentication.
*   **`api/vehicle-report.php`**: Backend PHP script that serves as an API endpoint for generating vehicle reports. It receives search criteria from the frontend, uses `VehicleReportClient` to fetch the report, and returns a JSON response.
*   **`api/vehicle-certificate.php`**: Backend PHP script that serves as an API endpoint for downloading PPSR certificates. It receives a certificate ID, uses `VehicleReportClient` to fetch the certificate, and streams the PDF content.
*   **`vehicle_report_form.html`**: Frontend HTML file providing a web form for users to input vehicle search criteria.
*   **`script.js`**: Frontend JavaScript that handles form submissions, interacts with the backend PHP API endpoints (`fetch` calls), and updates the UI with results or error messages.
*   **`style.css`**: Basic CSS file for styling the `vehicle_report_form.html`.

## Setup and Configuration

### Requirements

*   PHP version 7.2 or higher.
*   PHP cURL extension enabled (for making HTTP requests).
*   A web server (e.g., Apache, Nginx) capable of running PHP scripts.

### API Credentials

To connect to the InfoAgent API, you need a `client_id` and `client_secret`.

*   **Current Setup:** In this demonstration project, the `client_id` and `client_secret` are hardcoded directly in:
    *   `api/vehicle-report.php`
    *   `api/vehicle-certificate.php`

*   **IMPORTANT (Production):** For a production environment, **do not hardcode credentials directly in the code.** Instead, configure them securely using:
    *   Environment variables (recommended).
    *   A secure configuration file that is not committed to version control (e.g., outside the web root or with restricted permissions).

    You would then modify the PHP scripts to read these credentials from the chosen secure source.

## Running the Application

1.  **Deploy Files:** Place all project files (PHP classes, `api` directory, HTML, JS, CSS) into a directory served by your PHP-capable web server (e.g., `htdocs` for Apache, or a configured virtual host directory).
2.  **Set Credentials:**
    *   For testing, you can update the hardcoded `$clientId` and `$clientSecret` variables in `api/vehicle-report.php` and `api/vehicle-certificate.php` with your actual InfoAgent API credentials.
    *   Remember the security recommendation above for production.
3.  **Access the Form:** Open `vehicle_report_form.html` in your web browser. If you placed the project in the root of your web server, this would typically be `http://localhost/vehicle_report_form.html` or `http://your-server-ip/vehicle_report_form.html`.

## API Endpoints (for reference)

These are the backend endpoints created by this project, which the frontend interacts with.

*   **`POST /api/vehicle-report.php`**
    *   **Purpose:** Generates a vehicle report.
    *   **Request Payload (JSON):**
        ```json
        {
            "searchType": "vin", // or "plateAndState", "chassisAndMake"
            "searchParameters": {
                // Examples:
                // "vin": "YOUR_VIN_HERE"
                // "plateNumber": "ABC123", "state": "NSW"
                // "chassisNumber": "CHASSIS123", "make": "Toyota"
            }
        }
        ```
    *   **Success Response (JSON, 200 OK):**
        ```json
        {
            "success": true,
            "reportData": { /* ... actual report data from InfoAgent API ... */ },
            "certificateId": "CERTIFICATE_ID_IF_AVAILABLE" // or null
        }
        ```
    *   **Error Response (JSON, 4xx/5xx):**
        ```json
        {
            "success": false,
            "message": "Error description here"
        }
        ```

*   **`GET /api/vehicle-certificate.php?id={certificate_id}`**
    *   **Purpose:** Downloads a PPSR certificate.
    *   **Query Parameter:** `id` = The certificate ID obtained from a report.
    *   **Success Response (200 OK):** Raw PDF binary data with `Content-Type: application/pdf` and `Content-Disposition: attachment`.
    *   **Error Response (JSON, 4xx/5xx):**
        ```json
        {
            "success": false,
            "message": "Error description here"
        }
        ```

---

This README provides a basic guide for understanding, setting up, and using this InfoAgent API integration project.

## Integrating as a Separate Test Page

This project can be integrated as a standalone feature or test page into an existing PHP website. Here's how to do it, assuming your site uses a common `index.php?page=...` routing pattern:

**1. File Placement:**

*   **PHP Client Libraries:**
    *   Create a `lib/` directory at your website's web root if it doesn't already exist.
    *   Place `InfoAgentAuthClient.php` and `VehicleReportClient.php` into this `lib/` directory.
*   **API Handlers:**
    *   Place the entire `api/` directory (containing `vehicle-report.php` and `vehicle-certificate.php`) at your website's web root.
        *   The API endpoints will then be accessible via `/api/vehicle-report.php` and `/api/vehicle-certificate.php`.
        *   These scripts are already configured to find the client libraries in the `../lib/` directory.
*   **New Test Page:**
    *   Place the `infoagent_test_search.php` file in your website's web root. This file contains the HTML form.
*   **Frontend Assets:**
    *   Create a `js/` directory at your web root (if it doesn't exist) and place `script.js` inside it.
    *   Create a `css/` directory at your web root (if it doesn't exist) and place `style.css` (our form-specific styles) inside it.

**Summary of new file/folder locations (relative to web root):**

```
/lib/InfoAgentAuthClient.php
/lib/VehicleReportClient.php
/api/vehicle-report.php
/api/vehicle-certificate.php
/infoagent_test_search.php
/js/script.js
/css/style.css
```

**2. Styling Integration:**

*   Open `infoagent_test_search.php`.
*   Locate the following commented-out line:
    ```html
    <!-- TODO: Replace 'path/to/your/main_site_stylesheet.css' with the actual path to your website's main CSS file for consistent styling -->
    <!-- <link rel="stylesheet" href="path/to/your/main_site_stylesheet.css"> -->
    ```
*   Uncomment this line and change `path/to/your/main_site_stylesheet.css` to the correct path of your website's main stylesheet (e.g., `/css/theme.css`, `/style.css`). This will help the test page adopt your site's look and feel. Our specific styles for the form elements are in `/css/style.css` and are already linked.

**3. Making the Test Page Accessible:**

*   To access the new test page, you'll likely need to modify your main `index.php` (or a shared layout/menu file) to include a link to it, or handle it in your page routing logic.
*   For example, you could add a link in your site's navigation:
    ```html
    <a href="index.php?page=infoagent_test_search">InfoAgent Test Search</a>
    ```
*   If your `index.php` loads page content files based on the `?page=` parameter, it might look for `infoagent_test_search.php` when `page=infoagent_test_search` is in the URL. Ensure your `index.php` can correctly include `infoagent_test_search.php` from the web root (or adjust its include logic if you place `infoagent_test_search.php` in a `pages/` subdirectory).

**4. Configuration (Important Reminders):**

*   **API Credentials:** The `client_id` and `client_secret` for the InfoAgent API are currently hardcoded in `api/vehicle-report.php` and `api/vehicle-certificate.php`.
    *   **For production or any sensitive environment, YOU MUST move these credentials out of the code and into a secure configuration file (e.g., `.env` file, non-web-accessible PHP config) or environment variables.** The README's main "Setup and Configuration" section provides general advice on this.
*   **PHP cURL Extension:** Ensure your PHP installation has the cURL extension enabled, as it's required by the PHP client libraries to make HTTP requests.
*   **Error Reporting:** For a live test, ensure PHP error reporting is configured appropriately on your server (e.g., logging errors to a file rather than displaying them to users, which is good for debugging during setup but not for live).

**5. Testing:**

*   Navigate to the URL that loads `infoagent_test_search.php` (e.g., `yourdomain.com/index.php?page=infoagent_test_search` or `yourdomain.com/infoagent_test_search.php` if accessed directly).
*   Use the form with the test data provided by InfoAgent or your own test cases.
*   Check your browser's developer console for any JavaScript errors.
*   Check your server's PHP error logs for any backend issues.

This setup provides the InfoAgent integration as a distinct page, minimizing impact on your existing website code while allowing full testing of the functionality.
