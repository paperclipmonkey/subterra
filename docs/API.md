# Subterra API Documentation

This document provides detailed information about the Subterra API endpoints, authentication, and usage.

## Base URL
- Development: `http://localhost/api`
- Production: `https://subterra.world/api`

## Authentication

The API uses Laravel Sanctum for authentication. Users must authenticate via Google OAuth to access protected endpoints.

### OAuth Flow
1. Users authenticate via Google OAuth at `/auth/google`
2. After successful authentication, users receive a session
3. API requests are authenticated via session cookies

### Authentication Status
- `GET /api/users/me` - Get current authenticated user information

## Core Resources

### Caves

Caves represent individual cave entrances within cave systems.

#### List Caves
```http
GET /api/caves
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Main Entrance",
      "slug": "main-entrance",
      "description": "Primary entrance to the cave system",
      "cave_system_id": 1,
      "location_name": "Ingleborough",
      "location_country": "UK",
      "location_lat": 54.1234,
      "location_lng": -2.1234,
      "location_alt": 350.5,
      "access_info": "Access via public footpath",
      "hero_image": "caves/hero_123.webp",
      "entrance_image": "caves/entrance_123.webp",
      "length": 1500.0,
      "depth": 120.0,
      "tags": [...]
    }
  ]
}
```

#### Get Cave Details
```http
GET /api/caves/{cave}
```

#### Create Cave (Admin Only)
```http
POST /api/caves
Content-Type: application/json

{
  "name": "New Cave Entrance",
  "description": "Description of the cave",
  "cave_system_id": 1,
  "location_name": "Location Name",
  "location_country": "UK",
  "latitude": 54.1234,
  "longitude": -2.1234,
  "length": 1000.0,
  "depth": 50.0,
  "access_info": "Access information"
}
```

#### Update Cave (Admin Only)
```http
PUT /api/caves/{cave}
Content-Type: application/json

{
  "name": "Updated Cave Name",
  "description": "Updated description",
  "tags": [
    {
      "category": "region",
      "tag": "Yorkshire"
    }
  ],
  "hero_image": {
    "data": "data:image/jpeg;base64,..."
  }
}
```

### Cave Systems

Cave systems represent collections of connected caves.

#### Get Cave System Details
```http
GET /api/cave_systems/{cave_system}
```

#### Update Cave System (Admin Only)
```http
PUT /api/cave_systems/{cave_system}
Content-Type: application/json

{
  "name": "Updated System Name",
  "description": "Updated description",
  "length": 5000,
  "vertical_range": 200
}
```

#### Create Cave System with Cave (Admin Only)
```http
POST /api/cave_systems_with_cave
Content-Type: application/json

{
  "cave_system": {
    "name": "New Cave System",
    "description": "System description"
  },
  "cave": {
    "name": "Main Entrance",
    "description": "Entrance description"
  }
}
```

### Trips

Trips represent caving expeditions with participants.

#### List All Trips
```http
GET /api/trips
```

#### Get User's Trips
```http
GET /api/me/trips
```

#### Create Trip
```http
POST /api/trips
Content-Type: application/json

{
  "name": "Weekend Caving Trip",
  "description": "Great trip to explore the cave",
  "cave_system_id": 1,
  "entrance_cave_id": 1,
  "exit_cave_id": 1,
  "start_time": "2024-01-15 09:00:00",
  "end_time": "2024-01-15 15:00:00",
  "participants": [1, 2, 3],
  "media": [
    {
      "data": "data:image/jpeg;base64,..."
    }
  ]
}
```

#### Update Trip
```http
PUT /api/trips/{trip}
Content-Type: application/json

{
  "name": "Updated Trip Name",
  "description": "Updated description",
  "participants": [1, 2, 4],
  "existing_media": [
    {"id": 1}
  ],
  "media": [
    {
      "data": "data:image/jpeg;base64,..."
    }
  ]
}
```

#### Delete Trip
```http
DELETE /api/trips/{trip}
```

#### Download User's Trips as CSV
```http
GET /api/me/trips/download
```

### Users

User management and profile information.

#### List Users
```http
GET /api/users
```

#### Get User Details
```http
GET /api/users/{user}
```

#### Update User
```http
PUT /api/users/{user}
Content-Type: application/json

{
  "name": "Updated Name",
  "bio": "Updated bio information"
}
```

#### Get User's Recent Trips
```http
GET /api/users/{user}/recent-trips
```

#### Get User's Activity Heatmap
```http
GET /api/users/{user}/activity-heatmap
```

#### Get User's Medals
```http
GET /api/users/{user}/medals
```

### Clubs

Caving clubs and memberships.

#### List Clubs
```http
GET /api/clubs
```

#### Get Club Details
```http
GET /api/clubs/{club}
```

#### Request to Join Club
```http
POST /api/clubs/{club}/join
```

#### Get Club Members (Club Data)
```http
GET /api/clubs/{club}/members
```

#### Get Club Recent Trips
```http
GET /api/clubs/{club}/recent-trips
```

#### Get Club Activity Heatmap
```http
GET /api/clubs/{club}/activity-heatmap
```

### Tags

System tags for categorizing caves and trips.

#### List All Tags
```http
GET /api/tags
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "tag": "Yorkshire",
      "category": "region",
      "type": "location",
      "description": "Caves in Yorkshire region",
      "assignable": true
    }
  ]
}
```

## Admin Endpoints

Admin-only endpoints for system management.

### User Management
```http
GET /api/admin/users
PUT /api/admin/users/{user}/toggle-approval
PUT /api/admin/users/{user}/toggle-admin
```

### Club Management
```http
GET /api/admin/clubs
POST /api/admin/clubs
PUT /api/admin/clubs/{club}
DELETE /api/admin/clubs/{club}
PUT /api/admin/clubs/{club}/toggle-active
GET /api/admin/clubs/{club}/members
PUT /api/admin/clubs/{club}/members
GET /api/admin/clubs/{club}/pending-members
PUT /api/admin/clubs/{club}/members/{user}/approve
PUT /api/admin/clubs/{club}/members/{user}/reject
```

## Error Responses

The API returns standard HTTP status codes:

- `200` - Success
- `201` - Created
- `204` - No Content
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Unprocessable Entity (Validation Error)
- `500` - Internal Server Error

### Validation Error Response
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email field must be a valid email address."]
  }
}
```

## Rate Limiting

The API applies standard Laravel rate limiting:
- General endpoints: 60 requests per minute
- Authentication endpoints: 5 requests per minute

## Data Formats

### Dates
All dates are in ISO 8601 format: `YYYY-MM-DD HH:MM:SS`

### Images
Images are uploaded as base64-encoded data URIs:
```json
{
  "hero_image": {
    "data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD..."
  }
}
```

### Coordinates
Geographic coordinates use decimal degrees:
- Latitude: -90 to 90
- Longitude: -180 to 180
- Altitude: meters above sea level

## Webhooks and Events

The system fires events for:
- Trip creation (`TripCreated`)
- Medal awards (`MedalAwarded`)
- Trip participant tagging (`TripParticipantTagged`)

## SDKs and Libraries

For JavaScript/TypeScript applications, consider using:
- Axios for HTTP requests
- Laravel Echo for real-time events (if WebSocket support is added)

## Support

For API support and questions:
- GitHub Issues: https://github.com/paperclipmonkey/subterra/issues
- Documentation: This file and inline code comments