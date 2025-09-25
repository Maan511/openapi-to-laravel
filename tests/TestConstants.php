<?php

class TestConstants
{
    public const DEFAULT_MAX_STRING_LENGTH = 255;

    public const DEFAULT_MIN_STRING_LENGTH = 1;

    public const PERFORMANCE_TIME_LIMIT_SECONDS = 5.0;

    public const TEMP_DIR_PREFIX = 'openapi_test_';

    // Validation constraint defaults for consistent testing
    public const TEST_MIN_STRING_LENGTH = 3;

    public const TEST_MAX_STRING_LENGTH = 50;

    public const TEST_MAX_BIO_LENGTH = 500;

    public const TEST_MAX_DESCRIPTION_LENGTH = 1000;

    public const TEST_MIN_AGE = 0;

    public const TEST_MAX_AGE = 120;

    public const TEST_MIN_SCORE = 0;

    public const TEST_MAX_SCORE = 100;

    public const TEST_MIN_ITEMS = 1;

    public const TEST_MAX_ITEMS = 5;

    // Memory usage limits for performance tests
    public const MEMORY_LIMIT_MB = 50;
}
