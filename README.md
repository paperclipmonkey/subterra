![Subterra Logo](frontend/src/assets/subterra.svg)

# Subterra 

### [Subterra.world](https://subterra.world) is a tool to help cavers plan upcoming trips and to track trips they've been on.

## Functionality
### Find new trips
The system has a list of cave entrances and systems, with a number of tags added against them. This allows users to filter potential trip options and to find a trip that works for them.
### Track completed trips
Completed trips can be added by a user to help them track their caving career. When entering a trip it's possible to add other trip participants.

## Deployment
The system is deployed to fly.io using GitHub pipelines.

### Database
for an interactive psql shell use `fly postgres connect -a subterra-db`.

or for local proxying, use `fly proxy 5433:5432 -a subterra-db`
```
create user subterra_admin with encrypted password '';
ALTER USER subterra_admin WITH SUPERUSER;
```

### Local development
You'll need Docker installed locally.

Local development can be accomplished with the dockerfile and node running locally:
```sh
docker run --rm \
-u "$(id -u):$(id -g)" \
-v "$(pwd):/var/www/html" \
-w /var/www/html \
laravelsail/php84-composer:latest \
composer install --ignore-platform-reqs


# The following command doesn't require PHP to be installed
vendor/bin/sail up

# Run the migration
docker exec -it subterra-laravel.test-1 php artisan migrate:fresh --seed

# Login to the application then update your user
$user = User::first(); $user->is_admin = true; $user->is_approved = true; $user->save();
# Manually approve your user's club status
$user = User::first(); $user->clubs->first()->pivot->status = 'approved'; $user->clubs->first()->pivot->save();
```

The frontend can then be accessed on `http://localhost:3000`, with the api being proxied through the frontend.

### DNS
https://admin.gandi.net/domain/8e5d26dc-8680-11ef-8ba7-00163e94b645/subterra.world/records


## Database schema

```mermaid
classDiagram
direction BT
class cave_system_tag {
   bigint cave_system_id
   bigint tag_id
}
class cave_systems {
   varchar(255) name
   varchar(255) slug
   text description
   integer length
   integer vertical_range
   bigint id
}
class cave_tag {
   bigint cave_id
   bigint tag_id
}
class caves {
   varchar(255) name
   varchar(255) slug
   text description
   bigint cave_system_id
   varchar(255) location_name
   varchar(255) location_country
   double precision location_lat
   double precision location_lng
   double precision location_alt  /* Altitude in meters */
   text access_info
   bigint id
   varchar(255) hero_image
   varchar(255) entrance_image
}

class tags {
   varchar(255) tag
   varchar(255) type
   varchar(255) category
   varchar(255) image
   text description
   bigint id
}
class trip_media {
   bigint trip_id
   varchar(255) filename
   bigint id
}
class trip_user {
   bigint trip_id
   bigint user_id
}
class trips {
   varchar(255) name
   text description
   bigint cave_system_id
   bigint entrance_cave_id
   bigint exit_cave_id
   timestamp(0) start_time
   timestamp(0) end_time
   bigint id
}
class users {
   varchar(255) name
   varchar(255) email
   varchar(255) photo
   timestamp(0) email_verified_at
   varchar(255) password
   varchar(255) club
   boolean is_active
   varchar(100) remember_token
   timestamp(0) created_at
   timestamp(0) updated_at
   boolean has_signed_up
   bigint id
}

cave_system_tag  -->  cave_systems : cave_system_id.id
cave_tag  -->  tags : tag_id.id
caves  -->  cave_systems : cave_system_id.id
cave_tag  -->  caves : cave_id.id
cave_system_tag  -->  tags : tag_id.id
trip_media  -->  trips : trip_id.id
trip_user  -->  trips : trip_id.id
trip_user  -->  users : user_id.id
trips  -->  caves : entrance_cave_id.id
trips  -->  caves : exit_cave_id.id
trips  -->  cave_systems : cave_system_id.id

```