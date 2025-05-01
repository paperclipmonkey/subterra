The system consists of a Laravel API in the root directory and a Vue.js frontend in the `frontend` directory. The API is built with Laravel and uses Sail for local development, while the frontend is built with Vue.js and uses Yarn for package management.

The API is responsible for managing cave systems and caves, including their metadata, tags, and images. The frontend provides a user interface for interacting with the API and displaying information about the cave systems and caves.

To run Laravel command you need to run them in the docker container. You can do this by running the following command:
```sh
docker exec -it subterra-laravel.test-1 COMMAND_TO_RUN 
```

Tests should be written for all API endpoints.

The frontend is using Vuetify as the UI library and Vue Router for routing.`