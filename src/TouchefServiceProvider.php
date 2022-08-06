<?php

namespace PittacusW\Touchef;

use Illuminate\Support\ServiceProvider;

class TouchefServiceProvider extends ServiceProvider {

 /**
  * Indicates if loading of the provider is deferred.
  *
  * @var bool
  */
 protected $defer = false;


 /**
  * Publish asset
  */
 public function boot() {
  $this->publishes([
                    __DIR__.'/../../config/config.php' => config_path('touchef.php'),
                   ]);
 }

 /**
  * Register the service provider.
  *
  * @return void
  */
 public function register() {
 }

 /**
  * Get the services provided by the provider.
  *
  * @return array
  */
 public function provides() {
 }

}
