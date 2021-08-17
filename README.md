# Script
## Step 1
Clone this repo into you shopware docker code dir, into the folder `appdaysdemo`
```shell
git clone git@github.com:shopware/AppTemplate.git appdaysdemo
```
folder name needs to match to use the manifest file provided here, otherwise the urls won't match
Notice: the folder name should be lowercase, as otherwise it leads to networking problems

start containers by
```shell
swdc up
```

install composer dependencies
```shell
swdc pshell appdaysdemo
composer install
```

adjust .env file to following content
```dotenv
APP_ENV=dev
APP_NAME=AppDaysDemo
APP_SECRET=myAppSecret
APP_URL=http://appdaysdemo.dev.localhost
DATABASE_URL=mysql://root:root@mysql:3306/appdaysdemo
```

create `appdaysdemo` DB manually over `http://db.localhost/` -> necessary because DB will be created by `swdc build` command automatically, which is only for shopware projects

run migrations:
```shell
swdc pshell appdaysdemo
bin/console doctrine:migrations:migrate
```

inside platform create `AppDaysDemo` folder under `custom/apps` and create following `manifest.xml` in that folder
```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/master/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>AppDaysDemo</name>
        <label>Demo App for Stripe Payments</label>
        <label lang="de-DE">Demo App für Stripe Payments</label>
        <description>Example App - Do not use in production</description>
        <description lang="de-DE">Beispiel App - Nicht im Produktivbetrieb verwenden</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.0</version>
        <license>MIT</license>
    </meta>

    <setup>
        <registrationUrl>http://appdaysdemo.dev.localhost/register</registrationUrl> <!-- replace local url with real one -->
        <secret>myAppSecret</secret>
    </setup>
</manifest>
```

install and activate app
```shell
swdc pshell platform
bin/console app:install --activate AppDaysDemo
```

