This is a WordPress module intended to work with PHP 5.4.


## Introduction

The goal is that this module will be generally useful and will allow
many different LTI and WordPress integrations to build off of one LTI
plugin that is easily extensible and allows for custom institutional
or business rules.


## Requirements

This plugin requires the OAuth library. You can install this via PECL.
On most systems this can be achieved with

    sudo pecl install oauth

After installing via PECL you will need to restart Apache.
.

## Functionality

This module then tracks incoming LTI requests does all of the necessary validation and housekeeping including;

- Ensure that the payload and payload signatures match.
- Ensure the timestamp is within an acceptable range. Default window is the LTI recommended 90 minutes.
- Ensure that the provided nonce has not been used previously within the timestamp window to prevent replay attacks.
- Hand off execution at various points to external WordPress plugins implementing the appropriate actions.

## Documentation

After installing the module users can create LTI consumer posts which generate the LTI secret and key pair for use in the LTI consumer application.

## Developers

This module currently exposes the following three hooks that are all called in succession. The plan here is to allow multiple modules to respond to LTI launches. All of the following three hooks can be implemented in your module by running the following code during your plugin initialization.

    add_action( 'lti_launch', YOUR_FUNCTION_TO_LAUNCH );

Similarly you can do the same for the other two actions this plugin invokes.

### lti_setup

The intent of this hook is to do any necessary account creation, site creation or generally any type of content, user or permissions that need to be configured before any subsequent steps should happen.

### lti_pre

The intent of this hook is to do any necessary steps such as authenticating a user, switching a user to a different site/blog, or tracking any necessary LTI details that may be needed after the user completes a given task.

### lti_launch

The intent of this hook is to do the actual LTI launch. It is assumed in most cases that you will likely only want to implement one lti_launch hook as that process likely results in redirecting the user to the intended destination.
