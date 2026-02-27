<?php

/**
 * PHPUnit & WordPress Test Stubs for IDE autocompletion
 * 
 * This file provides IDE hints for PHPUnit classes and methods.
 * It is NOT used at runtime.
 * 
 * @package MHMRentiva\Tests
 */

namespace PHPUnit\Framework {
    if (!class_exists('TestCase', false)) {
        abstract class TestCase
        {
            public function assertTrue(mixed $condition, string $message = ''): void {}
            public function assertFalse(mixed $condition, string $message = ''): void {}
            public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void {}
            public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void {}
            public function assertNotNull(mixed $actual, string $message = ''): void {}
            public function assertNull(mixed $actual, string $message = ''): void {}
            public function assertArrayHasKey(mixed $key, array $array, string $message = ''): void {}
            public function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void {}
            public function assertNotFalse(mixed $condition, string $message = ''): void {}
            public function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void {}
            public function fail(string $message = ''): void {}

            /** @return void */
            public function setUp(): void {}
            /** @return void */
            public function tearDown(): void {}
            /** @return void */
            public function set_up() {}
            /** @return void */
            public function tear_down() {}

            /** @return \PHPUnit\Framework\MockObject\MockObject|mixed */
            public function createMock(string $originalClassName): object
            {
                return new \stdClass();
            }
        }
    }
}

namespace PHPUnit\Framework\MockObject {
    if (!interface_exists('MockObject', false)) {
        interface MockObject
        {
            public function method(string $name): self;
            public function willReturn(mixed $value): self;
        }
    }
}

namespace {
    /**
     * WordPress Test Case Stub
     */
    if (!class_exists('WP_UnitTestCase', false)) {
        class WP_UnitTestCase extends \PHPUnit\Framework\TestCase
        {
            /** @var WP_UnitTest_Factory */
            public $factory;

            public function setUp(): void {}
            public function tearDown(): void {}
            public function set_up() {}
            public function tear_down() {}
        }
    }

    /**
     * WordPress Test Factory Stubs
     */
    if (!class_exists('WP_UnitTest_Factory', false)) {
        class WP_UnitTest_Factory
        {
            /** @var WP_UnitTest_Factory_For_Post */
            public $post;
            /** @var WP_UnitTest_Factory_For_User */
            public $user;
            /** @var WP_UnitTest_Factory_For_Term */
            public $term;
        }

        class WP_UnitTest_Factory_For_Post
        {
            public function create(array $args = []): int
            {
                return 0;
            }
        }

        class WP_UnitTest_Factory_For_User
        {
            public function create(array $args = []): int
            {
                return 0;
            }
        }

        class WP_UnitTest_Factory_For_Term
        {
            public function create(array $args = []): int
            {
                return 0;
            }
        }
    }

    /**
     * WordPress Die Exception (both common formats)
     */
    if (!class_exists('WP_Die_Exception', false)) {
        class WP_Die_Exception extends \Exception {}
    }
    if (!class_exists('WP_DieException', false)) {
        class WP_DieException extends \Exception {}
    }


    if (!function_exists('tests_add_filter')) {
        function tests_add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {}
    }

    /**
     * Stub for PHPUnit\Framework\TestCase documentation purposes
     */
    class PHPUnit_Framework_TestCase_Stub {}
}
