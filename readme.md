# TPV clients

## Dev Environment Setup

Ensure node modules are installed.

> npm install

Copy Sample Dev environment and create test db

> cp env.dev .env && touch database/site.db

## Template Basics

The main template has support for 4 sections: 
 
 - title - The Title of the page
 - content - The Main Content of the page
 - scripts - Any script files/blocks specific to this page
 - head - Any thing that should go in the head tag, i.e. styles

See resources/views/dashboard.blade.php for an example

Blade Template Docs are found here: https://laravel.com/docs/5.4/blade

## Dev Server

To run the local server use

> ./artisan serve

## Global Styles

Global styles are in Sass and are located in resources/sass. Custom styles should go into the custom.scss file.
When changes to this file are made you need to run webpack to build the output files.

> npm run production

You can also setup a watch to automatically recompile the files with the command:

> npm run watch

or

> npm run watch-poll

## Laravel Basics

### Database

#### Creating Models

> ./artisan make:model --migration Models/MyModelName

This will create the stock model in app/Models and a migration file in database/migrations for schema setup.
Tables are automatically put into snake_case from CamelCase model names.

#### Updating a DB Table

> ./artisan make:migration --table=my_model_name name_of_migration 

Make the name of the migration short and memorable but don't include time or date info
Full migration docs: https://laravel.com/docs/5.4/migrations

#### Migrations

After the migration is created to install it use

> ./artisan migrate

And if you find an issue and want to undo the last migration use

> ./artisan migrate:rollback

### Controllers

Controllers are created with the command:

> ./artisan make:controller NameOfController/PathSupported/Too

This command will create a controller in app/Http/Controllers/NameOfController/PathSupported/Too.php.

## Other Helpful Laravel Doc Pages

Main https://laravel.com/docs/5.4/

Facades https://laravel.com/docs/5.4/facades

Helpers https://laravel.com/docs/5.4/helpers

Database Queries https://laravel.com/docs/5.4/queries

Eloquent Collections https://laravel.com/docs/5.4/eloquent-collections

Testing https://laravel.com/docs/5.4/testing