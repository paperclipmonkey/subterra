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
```
The frontend can then be accessed on `http://localhost:3000`, with the api being proxied through the frontend.
```sh
# Run a tinker terminal (php)
docker exec -it subterra-laravel.test-1 php artisan tinker
# Once you've followed the Oauth flow and logged into the application and requested approval for club membership
$user = User::first(); $user->is_admin = true; $user->is_approved = true; $user->save();
# Manually approve your user's club status
$user = User::first(); $user->clubs->first()->pivot->status = 'approved'; $user->clubs->first()->pivot->save();
```


### DNS
https://admin.gandi.net/domain/8e5d26dc-8680-11ef-8ba7-00163e94b645/subterra.world/records


## Database schema

```mermaid
classDiagram
direction BT
class audits {
   varchar(255) user_type
   bigint user_id
   varchar(255) event
   varchar(255) auditable_type
   bigint auditable_id
   text old_values
   text new_values
   text url
   inet ip_address
   varchar(1023) user_agent
   varchar(255) tags
   timestamp(0) created_at
   timestamp(0) updated_at
   bigint id
}
class cache {
   text value
   integer expiration
   varchar(255) key
}
class cache_locks {
   varchar(255) owner
   integer expiration
   varchar(255) key
}
class cave_system_files {
   bigint cave_system_id
   varchar(255) filename
   text details
   varchar(255) original_filename
   varchar(255) mime_type
   bigint size
   timestamp(0) created_at
   timestamp(0) updated_at
   bigint id
}
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
   text references
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
   varchar(255) hero_image
   varchar(255) entrance_image
   bigint id
}
class club_user {
   bigint club_id
   bigint user_id
   boolean is_admin
   timestamp(0) created_at
   timestamp(0) updated_at
   varchar(255) status
   bigint id
}
class clubs {
   varchar(255) name
   varchar(255) slug
   text description
   varchar(255) website
   varchar(255) location
   boolean is_active
   timestamp(0) created_at
   timestamp(0) updated_at
   bigint id
}
class failed_jobs {
   varchar(255) uuid
   text connection
   text queue
   text payload
   text exception
   timestamp(0) failed_at
   bigint id
}
class job_batches {
   varchar(255) name
   integer total_jobs
   integer pending_jobs
   integer failed_jobs
   text failed_job_ids
   text options
   integer cancelled_at
   integer created_at
   integer finished_at
   varchar(255) id
}
class jobs {
   varchar(255) queue
   text payload
   smallint attempts
   integer reserved_at
   integer available_at
   integer created_at
   bigint id
}
class medal_user {
   bigint user_id
   bigint medal_id
   timestamp(0) awarded_at
   timestamp(0) created_at
   timestamp(0) updated_at
   bigint id
}
class medals {
   varchar(255) name
   varchar(255) description
   varchar(255) image_path
   timestamp(0) created_at
   timestamp(0) updated_at
   bigint id
}
class migrations {
   varchar(255) migration
   integer batch
   integer id
}
class tag_groups {
   timestamp(0) created_at
   timestamp(0) updated_at
   bigint id
}
class tag_trip {
   bigint tag_id
   bigint trip_id
   timestamp(0) created_at
   timestamp(0) updated_at
   bigint id
}
class tags {
   varchar(255) tag
   varchar(255) type
   varchar(255) category
   varchar(255) image
   text description
   boolean assignable
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
   boolean is_active
   boolean is_approved
   timestamp(0) created_at
   timestamp(0) updated_at
   boolean is_admin
   text bio
   bigint id
}

cave_system_tag  -->  cave_systems : cave_system_id.id
cave_system_tag  -->  tags : tag_id.id
cave_tag  -->  caves : cave_id.id
cave_tag  -->  tags : tag_id.id
caves  -->  cave_systems : cave_system_id.id
cave_system_files  -->  cave_systems : cave_system_id.id
club_user  -->  clubs : club_id.id
club_user  -->  users : user_id.id
medal_user  -->  medals : medal_id.id
medal_user  -->  users : user_id.id
tag_trip  -->  tags : tag_id.id
tag_trip  -->  trips : trip_id.id
trip_media  -->  trips : trip_id.id
trip_user  -->  trips : trip_id.id
trip_user  -->  users : user_id.id
trips  -->  cave_systems : cave_system_id.id
trips  -->  caves : entrance_cave_id.id
trips  -->  caves : exit_cave_id.id
audits  -->  users : user_id.id

```