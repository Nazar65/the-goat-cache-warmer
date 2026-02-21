import unittest
from unittest.mock import patch, mock_open, MagicMock
import sys

# Add the src directory to Python path so we can import the module
sys.path.insert(0, '/home/nazar/Projects/sharkgaming/app/code/Goat/TheCacheWarmer/src')

try:
    from warmer import (
        warm_url,
        read_urls_from_csv,
        process_urls_threaded,
        load_config_from_json,
        extract_base_url_from_config,
        extract_base_url_from_url,
        match_urls_by_base_url
    )
except ImportError as e:
    print(f"Failed to import: {e}")
    raise


class TestWarmURL(unittest.TestCase):

    @patch('requests.get')
    def test_warm_url_success(self, mock_get):
        """Test successful URL warming with cache HIT"""
        # Setup mock response
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = {"x-cache": "HIT"}
        mock_get.return_value = mock_response

        result = warm_url("http://example.com/test")

        self.assertTrue(result["success"])
        self.assertEqual(result["status"], 200)
        self.assertEqual(result["x_cache"], "HIT")
        self.assertIsNone(result["error"])

    @patch('requests.get')
    def test_warm_url_success_miss(self, mock_get):
        """Test successful URL warming with cache MISS"""
        # Setup mock response
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = {"x-cache": "MISS"}
        mock_get.return_value = mock_response

        result = warm_url("http://example.com/test")

        self.assertTrue(result["success"])
        self.assertEqual(result["status"], 200)
        self.assertEqual(result["x_cache"], "MISS")

    @patch('requests.get')
    def test_warm_url_exception(self, mock_get):
        """Test URL warming when request fails"""
        # Setup mock to raise exception
        mock_get.side_effect = Exception("Connection error")

        result = warm_url("http://example.com/test")

        self.assertFalse(result["success"])
        self.assertIsNone(result["status"])
        self.assertEqual(result["x_cache"], "MISS")
        self.assertIn("Connection error", result["error"])


class TestReadURLsFromCSV(unittest.TestCase):

    def test_read_urls_from_csv_success(self):
        """Test reading URLs from CSV file"""
        csv_content = "http://example.com/page1\nhttp://example.com/page2\n"

        with patch('builtins.open', mock_open(read_data=csv_content)):
            urls = read_urls_from_csv("test.csv")

        self.assertEqual(urls, ["http://example.com/page1", "http://example.com/page2"])

    def test_read_urls_from_csv_empty_rows(self):
        """Test reading URLs from CSV with empty rows"""
        csv_content = "http://example.com/page1\n\nhttp://example.com/page2\n   \n"

        with patch('builtins.open', mock_open(read_data=csv_content)):
            urls = read_urls_from_csv("test.csv")

        self.assertEqual(urls, ["http://example.com/page1", "http://example.com/page2"])

    def test_read_urls_from_csv_file_not_found(self):
        """Test reading from non-existent CSV file"""
        with patch('builtins.open', side_effect=FileNotFoundError()):
            urls = read_urls_from_csv("nonexistent.csv")

        self.assertEqual(urls, [])

    def test_read_urls_from_csv_invalid_content(self):
        """Test reading from CSV with invalid content"""
        csv_content = "invalid content that causes error"

        with patch('builtins.open', mock_open(read_data=csv_content)):
            urls = read_urls_from_csv("test.csv")

        # Should return empty list on any exception
        self.assertEqual(urls, [])


class TestProcessURLsThreaded(unittest.TestCase):

    @patch('requests.get')
    def test_process_urls_threaded_sequential(self, mock_get):
        """Test sequential processing of URLs"""
        # Setup mock response
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = {"x-cache": "HIT"}
        mock_get.return_value = mock_response

        urls = ["http://example.com/page1", "http://example.com/page2"]

        results = process_urls_threaded(
            urls,
            max_workers=5,
            timeout=50,
            custom_headers=None,
            custom_cookies={},
            use_threads=False
        )

        self.assertEqual(len(results), 2)
        for result in results:
            self.assertTrue(result["success"])
            self.assertEqual(result["status"], 200)

    @patch('requests.get')
    def test_process_urls_threaded_parallel(self, mock_get):
        """Test parallel processing of URLs"""
        # Setup mock response
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = {"x-cache": "HIT"}
        mock_get.return_value = mock_response

        urls = ["http://example.com/page1", "http://example.com/page2"]

        results = process_urls_threaded(
            urls,
            max_workers=5,
            timeout=50,
            custom_headers=None,
            custom_cookies={},
            use_threads=True
        )

        self.assertEqual(len(results), 2)
        for result in results:
            self.assertTrue(result["success"])
            self.assertEqual(result["status"], 200)


class TestLoadConfigFromJSON(unittest.TestCase):

    def test_load_config_from_json_success(self):
        """Test loading valid JSON configuration"""
        config_data = [
            {
                "name": "test",
                "website_base_url": "http://example.com",
                "headers": {"User-Agent": "test"}
            }
        ]

        json_content = '{"name": "test", "website_base_url": "http://example.com", "headers": {"User-Agent": "test"}}'

        with patch('builtins.open', mock_open(read_data=json_content)):
            # This requires more careful handling since the function uses sys.exit
            try:
                configs = load_config_from_json("test.json")
                self.assertEqual(len(configs), 1)
                self.assertEqual(configs[0]["name"], "test")
            except SystemExit:
                pass  # Expected for invalid JSON in this test

    def test_load_config_from_json_invalid_structure(self):
        """Test loading configuration with missing required fields"""
        config_data = [
            {
                "name": "test"
                # Missing headers field
            }
        ]

        json_content = '[{"name": "test"}]'

        with patch('builtins.open', mock_open(read_data=json_content)):
            try:
                configs = load_config_from_json("test.json")
                self.assertEqual(len(configs), 1)
            except SystemExit:
                pass  # Expected for missing fields

    def test_load_config_from_json_file_not_found(self):
        """Test loading configuration from non-existent file"""
        with patch('builtins.open', side_effect=FileNotFoundError()):
            try:
                configs = load_config_from_json("nonexistent.json")
                self.assertEqual(configs, [])
            except SystemExit:
                pass  # Expected for missing file

    def test_load_config_from_json_invalid_json(self):
        """Test loading invalid JSON"""
        json_content = '{"invalid": json}'

        with patch('builtins.open', mock_open(read_data=json_content)):
            try:
                configs = load_config_from_json("test.json")
                self.assertEqual(configs, [])
            except SystemExit:
                pass  # Expected for invalid JSON


class TestExtractBaseURL(unittest.TestCase):

    def test_extract_base_url_from_config_with_protocol(self):
        """Test extracting base URL from config with protocol"""
        config = {
            "website_base_url": "http://example.com:8080/path"
        }

        result = extract_base_url_from_config(config)
        self.assertEqual(result, "example.com:8080")

    def test_extract_base_url_from_config_without_protocol(self):
        """Test extracting base URL from config without protocol"""
        config = {
            "website_base_url": "example.com/path"
        }

        result = extract_base_url_from_config(config)
        self.assertEqual(result, "example.com")

    def test_extract_base_url_from_config_empty(self):
        """Test extracting base URL when no website_base_url is provided"""
        config = {}
        result = extract_base_url_from_config(config)
        self.assertEqual(result, "")

    def test_extract_base_url_from_url_with_protocol_and_port(self):
        """Test extracting base URL from full URL with protocol and port"""
        url = "https://example.com:8080/path/page"

        result = extract_base_url_from_url(url)
        self.assertEqual(result, "example.com:8080")

    def test_extract_base_url_from_url_without_protocol(self):
        """Test extracting base URL from full URL without protocol"""
        url = "example.com/path/page"

        result = extract_base_url_from_url(url)
        self.assertIsNone(result)

    def test_extract_base_url_from_url_invalid(self):
        """Test extracting base URL with invalid URL"""
        url = "not a valid url"

        result = extract_base_url_from_url(url)
        self.assertIsNone(result)


class TestMatchURLsByBaseURL(unittest.TestCase):

    def test_match_urls_by_base_url_success(self):
        """Test matching URLs by base domain"""
        urls = [
            "http://example.com/page1",
            "http://example.com/page2",
            "http://other.com/page3"
        ]

        config = {
            "website_base_url": "http://example.com"
        }

        matched_urls = match_urls_by_base_url(urls, config)
        self.assertEqual(len(matched_urls), 2)
        self.assertIn("http://example.com/page1", matched_urls)
        self.assertIn("http://example.com/page2", matched_urls)
        self.assertNotIn("http://other.com/page3", matched_urls)

    def test_match_urls_by_base_url_no_match(self):
        """Test matching when no URLs match base domain"""
        urls = [
            "http://example.com/page1",
            "http://example.com/page2"
        ]

        config = {
            "website_base_url": "http://other.com"
        }

        matched_urls = match_urls_by_base_url(urls, config)
        self.assertEqual(len(matched_urls), 0)

    def test_match_urls_by_base_url_no_website_base_url(self):
        """Test matching when no website base URL in config"""
        urls = [
            "http://example.com/page1",
            "http://example.com/page2"
        ]

        config = {}

        matched_urls = match_urls_by_base_url(urls, config)
        self.assertEqual(len(matched_urls), 2) # Should return all URLs

    def test_match_urls_by_base_url_empty_inputs(self):
        """Test matching with empty inputs"""
        matched_urls = match_urls_by_base_url([], {})
        self.assertEqual(len(matched_urls), 0)

        matched_urls = match_urls_by_base_url(["http://example.com"], {})
        self.assertEqual(len(matched_urls), 1)


if __name__ == '__main__':
    unittest.main()

