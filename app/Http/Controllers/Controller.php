<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "NARA Academic Schedule & WhatsApp Reminder API",
    description: "REST API for managing semesters, courses, lecturers, PICs, schedules, and automated WhatsApp reminders via Fonnte.",
    contact: new OA\Contact(email: "admin@nara.local")
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "NARA API Server"
)]
#[OA\Tag(name: "Semesters", description: "API Endpoints for managing Academic Semesters")]
#[OA\Tag(name: "Lecturers", description: "API Endpoints for managing Lecturers")]
#[OA\Tag(name: "Course PICs", description: "API Endpoints for managing Course PICs")]
#[OA\Tag(name: "Courses", description: "API Endpoints for managing Courses")]
#[OA\Tag(name: "Schedules", description: "API Endpoints for managing Academic Class Schedules")]
#[OA\Tag(name: "Reminders", description: "API Endpoints for WhatsApp reminders and delivery logs")]
abstract class Controller
{
    //
}
