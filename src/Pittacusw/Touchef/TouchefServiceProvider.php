<?php

namespace Pittacusw\Touchef;

use Illuminate\Support\ServiceProvider;

class TouchefServiceProvider extends ServiceProvider {

 /**
  * Indicates if loading of the provider is deferred.
  *
  * @var bool
  */
 protected $defer = FALSE;

 /**
  * Publish asset
  */
 public function boot() {
  $this->publishes([
                    __DIR__ . '/../../config/config.php' => config_path('touchef.php'),
                   ]);
 }

 /**
  * Register the service provider.
  *
  * @return void
  */
 public function register() {
  $this->app->singleton('Touchef', function() {
   return new Touchef;
  });
 }

 /**
  * Get the services provided by the provider.
  *
  * @return array
  */
 public function provides() {
  return ["Touchef"];
 }

}
