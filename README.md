![Subterra Logo](frontend/src/assets/subterra.svg)

# Subterra 

### [Subterra.world](https://subterra.world) is a tool to help cavers plan upcoming trips and to track trips they've been on.

## Functionality
### Find new trips
The system has a list of cave entrances and systems, with a number of tags added against them. This allows users to filter potential trip options and to find a trip that works for them.
### Track completed trips
Completed trips can be added by a user to help them track their caving career. When entering a trip it's possible to add other trip participants by email. These people are then shown these trips once they log in.

## Development
The system is deployed to fly.io using GitHub pipelines.

### Database
for an interactive psql shell use `fly postgres connect -a subterra-db`.

or for local proxying, use `fly proxy 5433:5432 -a subterra-db`


```
create user subterra_admin with encrypted password '';
ALTER USER subterra_admin WITH SUPERUSER;
```

### Local development
Local development can be accomplished with the dockerfile and node running locally:
```sh
# To start the API server
docker compose up 
# To start the ui
cd frontend
yarn run dev
```

### DNS
https://admin.gandi.net/domain/8e5d26dc-8680-11ef-8ba7-00163e94b645/subterra.world/records


## Database schema

```mermaid
classDiagram
direction BT
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
class migrations {
   varchar(255) migration
   integer batch
   integer id
}
class password_reset_tokens {
   varchar(255) token
   timestamp(0) created_at
   varchar(255) email
}
class personal_access_tokens {
   varchar(255) tokenable_type
   bigint tokenable_id
   varchar(255) name
   varchar(64) token
   text abilities
   timestamp(0) last_used_at
   timestamp(0) expires_at
   timestamp(0) created_at
   timestamp(0) updated_at
   bigint id
}
class sessions {
   bigint user_id
   varchar(45) ip_address
   text user_agent
   text payload
   integer last_activity
   varchar(255) id
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

cave_system_tag  -->  cave_systems : cave_system_id:id
cave_system_tag  -->  tags : tag_id:id
cave_tag  -->  caves : cave_id:id
cave_tag  -->  tags : tag_id:id
caves  -->  cave_systems : cave_system_id:id
sessions  -->  users : user_id:id
trip_media  -->  trips : trip_id:id
trip_user  -->  trips : trip_id:id
trip_user  -->  users : user_id:id
trips  -->  cave_systems : cave_system_id:id
trips  -->  caves : entrance_cave_id:id
trips  -->  caves : exit_cave_id:id

```