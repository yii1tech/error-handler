<p align="center">
    <a href="https://github.com/yii1tech" target="_blank">
        <img src="https://avatars.githubusercontent.com/u/134691944" height="100px">
    </a>
    <h1 align="center">Yii1 Enhanced Error Handler</h1>
    <br>
</p>

This extension provides Enhanced Error Handler for Yii1 application.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://img.shields.io/packagist/v/yii1tech/error-handler.svg)](https://packagist.org/packages/yii1tech/error-handler)
[![Total Downloads](https://img.shields.io/packagist/dt/yii1tech/error-handler.svg)](https://packagist.org/packages/yii1tech/error-handler)
[![Build Status](https://github.com/yii1tech/error-handler/workflows/build/badge.svg)](https://github.com/yii1tech/error-handler/actions)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii1tech/error-handler
```

or add

```json
"yii1tech/error-handler": "*"
```

to the "require" section of your composer.json.


Usage
-----

This extension provides Enhanced Error Handler for Yii1 application.
Its main feature is conversion of the PHP errors into exceptions, so they may be processed via `try..catch` blocks.

> Note: in order for error to exception conversion to work, the error handler component should be added to the
  application "preload" section.

Application configuration example:

```php
<?php

return [
    'preload' => [
        'errorHandler', // preload custom error handler, overriding the default one, allowing error to exception conversion
        // ...
    ],
    'components' => [
        'errorHandler' => [
            'class' => \yii1tech\error\handler\ErrorHandler::class,
        ],
        // ...
    ],
    // ...
];
```

Once configured `\yii1tech\error\handler\ErrorHandler` allows you to catch PHP errors as exceptions.
For example:

```php
<?php

try {
    // ...
    trigger_error('Some custom error message', E_USER_WARNING);
    // ...
} catch (ErrorException $exception) {
    // handle error
}
```

In addition, `\yii1tech\error\handler\ErrorHandler` provides support for error/exception rendering as JSON output, 
which is useful for modern XHR and API implementation.
By default, the error will be rendered as JSON only in case the HTTP client passes header "Accept" with matching MIME type -
"application/json". However, you may control this behavior using `\yii1tech\error\handler\ErrorHandler::$shouldRenderErrorAsJsonCallback`.
For example:

```php
<?php

return [
    'preload' => [
        'errorHandler', // preload custom error handler, overriding the default one, allowing error to exception conversion
        // ...
    ],
    'components' => [
        'errorHandler' => [
            'class' => \yii1tech\error\handler\ErrorHandler::class,
            'shouldRenderErrorAsJsonCallback' => function () {
                if (!empty($_SERVER['HTTP_ACCEPT']) && strcasecmp($_SERVER['HTTP_ACCEPT'], 'application/json') === 0) {
                    return true;
                }
                
                if (Yii::app()->request->isAjaxRequest) {
                    return true;
                }
                
                if (str_starts_with(Yii::app()->request->getPathInfo(), 'api/')) {
                    return true;
                }
                
                return false;
            },
        ],
        // ...
    ],
    // ...
];
```

You may reuse the error handler ability to render errors as JSON in your custom error action, which is specified
via `\CErrorHandler::$errorAction`. For example:

```php
<?php

class SiteController extends CController
{
    public function actionError(): void
    {
        /** @var \yii1tech\error\handler\ErrorHandler $errorHandler */
        $errorHandler = Yii::app()->getErrorHandler();
        if ($errorHandler->shouldRenderErrorAsJson()) {
            // render JSON error representation.
            $errorHandler->renderErrorAsJson();
            
            return;
        }
        
        // Render HTML error representation:
        if ($error = $errorHandler->error) {
            $this->render('error', $error);
        }
    }
}
```
