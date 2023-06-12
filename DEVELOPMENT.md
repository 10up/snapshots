This file includes details for developers contributing to this code base.

## Code Architecture

This package follows a simple dependency-injection pattern. There are two types of components within this pattern. Both are registered in [the Plugin class](/includes/classes/Plugin.php).

1. Modules: Modules are loaded on every execution. The implement the `Module` interface and include a `register` method that performs setup actions. The `register` method is called automatcically when the module is instantiated.
2. Services: Services are loaded when they are declared as dependencies for Modules and other Services. They implement the `Service` interface. If a service should only be instantiated once and shared throughout the system, it should instead implement the `SharedService` interace.

### Declaring Dependencies

Once a Service has been declared in [the Plugin class](/includes/classes/Plugin.php) and implements either `Service` or `SharedService`, it will automatically be passed to any module or service in the system that declares it via a type hint in its own constructor. For example:


```PHP
// Service class added to the services array in Plugin.php
class MyService implements Service {
    public function say_hello() {
        echo 'hello';
    }
}

class MyModule implements Module {
    private $my_service;

    public function construct( Service $my_service ) { // Automatically passed in.
        $this->my_service = $my_service;
    }

    public function register() {
        // setup actions
    }

    public function greet() {
        $this->my_service->say_hello();
    }
}
```

A dependency can also be an interface. The system only allows one implementation of an interface to be registered at a time. For example:

```PHP
interface MyInterface {
    public function say_hello();
}

class MyService implements Service, MyInterface {
    public function say_hello() {
        echo 'hello';
    }
}

class MyModule implements Module {
    private $my_service;

    public function construct( MyInterface $my_service ) { // Automatically passed in. The dependency injector will detect the concrete class that implements the interface.
        $this->my_service = $my_service;
    }

    public function register() {
        // setup actions
    }

    public function greet() {
        $this->my_service->say_hello();
    }
}
```

## Unit Testing

Unit tests run inside a GitHub Action on Pull Requests. Running tests locally requires Docker. Once Docker is running, run tests with the following commands:

```bash
npm install # Installs the @wordpress/env package.
npm run env-start # Starts the WordPress environment.
npm run test:php # Runs the PHP unit tests.
```
