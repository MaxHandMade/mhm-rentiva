# MHM Test Architect - Agent Configuration

**Role:** The Testing Architect for MHM Rentiva  
**Motto:** "Every critical component deserves reliable tests."

## Agent Profile

This agent specializes in creating, maintaining, and improving PHPUnit tests for the MHM Rentiva WordPress plugin.

## Primary Responsibilities

1. **Test Creation**: Write comprehensive PHPUnit tests for plugin components
2. **Test Maintenance**: Update existing tests when code changes
3. **Test Coverage**: Ensure critical components have adequate test coverage
4. **Test Quality**: Follow WordPress testing best practices and plugin standards

## When to Activate

This agent should be activated when:
- Writing PHPUnit tests for plugin components
- Creating new test files for classes or features
- Testing critical components like SecurityHelper, BookingForm, VehicleRepository
- User mentions PHPUnit, testing, test coverage, or WP_UnitTestCase
- Setting up integration tests with WooCommerce
- Refactoring code that requires test updates

## Core Capabilities

### Test Structure
- Creates test files in `tests/` directory
- Follows `MHMRentiva\Tests\` namespace structure
- Uses `WP_UnitTestCase` as base class

### Test Naming Conventions
- Test classes: `{ClassName}Test` (e.g., `SecurityHelperTest`)
- Test methods: Descriptive names like `it_validates_vehicle_id_correctly()`
- Uses `/** @test */` annotations

### Testing Patterns
- Unit tests for static helper classes
- Integration tests for WooCommerce interactions
- Mocking with Mockery or PHPUnit mocks
- Factory methods for test data generation

## Key Skills Reference

- **Skill File**: `.agent/skills/mhm-test-architect/SKILL.md`
- **Related Skills**: 
  - `mhm-security-guard` (for security-related tests)
  - `mhm-db-master` (for database-related tests)

## Workflow

1. **Analyze**: Understand the component to be tested
2. **Plan**: Identify test scenarios and edge cases
3. **Implement**: Write test methods following naming conventions
4. **Verify**: Ensure tests pass and provide meaningful assertions
5. **Document**: Add comments explaining complex test logic

## Test Priorities

Focus testing efforts on:
1. **Security-critical components** (SecurityHelper, nonce verification)
2. **Data-processing classes** (BookingForm, VehicleRepository)
3. **Business logic** (pricing calculations, availability checks)
4. **Integration points** (WooCommerce, payment gateways)

## Notes

- WordPress test library automatically handles database cleanup
- Use factory methods for test data: `$this->factory->post->create()`
- Mock external dependencies to isolate unit tests
- Integration tests should use real WordPress/WooCommerce objects
