# Database Operations

The ` Database Operations` package provides a trait for Laravel models to handle database operations and track user
activity. It automatically populates database columns and simplifies the implementation of user tracking features.

## Installation

To install the `System Notifications` package, follow these steps:

1. Ensure that you have [Composer](https://getcomposer.org/) installed on your machine.

2. Open your terminal and navigate to your Laravel project's directory.

3. Run the following command to install the package:

```bash
composer require notifications/system-notifications
```
4. Run the following command to publish the config file named "fcm.php":

```bash
php artisan vendor:publish --provider="SystemNotifications\SystemNotificationsServiceProvider" --tag=system-notifications-config

```
5. In your Env file add Firebase configuration:

```bash
FCM_SERVER_KEY=your_firebase_key
FCM_SENDER_ID=your_firebase_sender_id
```

6. After the package is installed, Laravel will automatically discover the package and register its service provider.



## Usage

```php
        $variable['password'] = "testPassword@123";
        $variable['firstname'] = "Arslan Ayoub";
        $variable['email'] = "arsalanayoub48@gmail.com";
        NotificationHelper::initializeNotification(SEEKER , 252,"NewAccountPassword",$variable);
```
