# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2025-11-03

### Security
- Added `$hidden` properties to all models to prevent sensitive data exposure (Stripe IDs, payment details)
- Added `$guarded` properties to all models for mass assignment protection
- Implemented authorization policies for Subscription and Invoice access control
- Added comprehensive rate limiting to all API routes (60/min general, 10/min for subscription creation, 100/min for webhooks)
- Added Stripe API key validation on service provider boot
- Fixed invoice number race condition with database locking
- Enhanced webhook security with comprehensive error logging and monitoring
- Added security logging for failed webhook signature verifications

### Changed
- Updated SubscriptionController to use explicit authorization checks
- Updated InvoiceController to use explicit authorization checks
- PlanController now only exposes active plans via public API
- WebhookController now includes detailed security logging for all events and failures
- Routes file now includes CSRF exclusion documentation for webhooks

### Added
- Created SubscriptionPolicy for authorization
- Created InvoicePolicy for authorization
- Added input validation for subscription cancellation endpoint
- Added comprehensive error handling in WebhookController

## [0.1.0] - 2025-11-03

### Added
- Initial release
- Stripe-based subscription and billing management
- Support for multiple plans and add-ons
- Proration support
- Trial periods and grace periods
- Workspace-based billing (optional)
- Invoice generation and management
- Payment method management
- Webhook handling for Stripe events
- Usage-based billing support
- Daily billing processing command
- Comprehensive test suite with Pest
