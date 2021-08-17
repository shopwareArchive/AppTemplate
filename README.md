# App Server Template based on Symfony framework

This is an app server template for developing an app server. This should be forked and used as base for your app server

## Features

- Registration process prebuilt
- API client for communication with the Shopware shop

## Requirements

- PHP 8.0
- MySQL >= 5.7 or MariaDB

## Installation

Clone the repository and run `composer install` to install the dependencies

```bash
  git clone https://github.com/shopware/AppTemplate.git
  cd AppTemplate
  composer install
```

Configure the credentials in the `.env` file

- Configure database url (currently only MySQL supported)
- Configure your app name and app secret

```bash
    # Creates the database
    php bin/console doctrine:database:create

    # Runs the migrations
    php bin/console doctrine:migrations:migrate
```

and you are ready to go to add your custom logic.