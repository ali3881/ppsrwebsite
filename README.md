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
