# Unit Tests for Cache Warmer

This directory contains unit tests for the Python cache warmer script.

## Running the Tests

To run these tests, you need to have the required dependencies installed:

```bash
pip install requests unittest
```

Then run:

```bash
python -m unittest app/code/Goat/TheCacheWarmer/src/tests/test_warmer.py -v
```

## Test Coverage

The test suite covers all core functions of the cache warmer:

1. `warm_url()` - HTTP request handling and response parsing
2. `read_urls_from_csv()` - CSV file reading with error handling
3. `process_urls_threaded()` - Parallel and sequential URL processing
4. `load_config_from_json()` - JSON configuration loading and validation
5. `extract_base_url_from_config()` - Base URL extraction logic
6. `extract_base_url_from_url()` - Full URL parsing
7. `match_urls_by_base_url()` - URL matching by domain

## Test Types

- **Success scenarios**: Normal operation with valid inputs
- **Error handling**: File not found, invalid JSON, network failures
- **Edge cases**: Empty data, malformed URLs, missing fields
- **Mocking**: External dependencies like HTTP requests are mocked for controlled testing
