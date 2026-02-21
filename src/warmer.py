import argparse
import csv
import sys
import time
import json
from concurrent.futures import ThreadPoolExecutor
import warnings
import requests


def warm_url(url, headers=None, cookies=None, timeout=50):
    """Send a GET request to warm up a URL with specified headers and cookies"""
    try:
        warnings.simplefilter("ignore")
        response = requests.get(
            url, headers=headers, cookies=cookies, timeout=timeout, verify=False
        )
        # Extract x-cache header
        x_cache = response.headers.get("x-cache", "Not found")

        return {
            "url": url,
            "status": response.status_code,
            "success": True,
            "error": None,
            "x_cache": x_cache,
        }
    except Exception as e:
        return {
            "url": url,
            "status": None,
            "success": False,
            "error": str(e),
            "x_cache": "MISS",
        }


def read_urls_from_csv(filename):
    """Read URLs from CSV file (assuming first column contains URLs)"""
    urls = []
    try:
        with open(filename, "r", newline="", encoding="utf-8") as csvfile:
            reader = csv.reader(csvfile)
            for row in reader:
                if row:  # Skip empty rows
                    url = row[0].strip()
                    if url:  # Only add non-empty URLs
                        # Validate that the URL looks like a proper URL with scheme and netloc
                        try:
                            from urllib.parse import urlparse

                            parsed_url = urlparse(url)
                            if (
                                parsed_url.scheme in ("http", "https")
                                and parsed_url.netloc
                            ):
                                urls.append(url)
                        except Exception:
                            # If we can't parse it as a URL, skip it
                            continue
    except FileNotFoundError:
        print(f"Error: File '{filename}' not found.")
        return []
    except Exception as e:
        print(f"Error reading CSV file: {e}")
        return []
    return urls


def process_urls_threaded(
    urls,
    max_workers=5,
    timeout=50,
    custom_headers=None,
    custom_cookies=None,
    use_threads=True,
    rate_limit=None,
):
    """Process URLs in parallel using ThreadPoolExecutor or sequentially"""
    if not use_threads:
        # Sequential processing without threads
        results = []
        start_time = time.time()
        for i, url in enumerate(urls):
            # Apply rate limiting if specified and threading is disabled
            if rate_limit and i > 0:
                elapsed_time = time.time() - start_time
                target_elapsed = (
                    i / rate_limit * 60
                )  # seconds per URL based on rate limit
                sleep_time = max(0, target_elapsed - elapsed_time)
                if sleep_time > 0:
                    time.sleep(sleep_time)

            result = warm_url(url, custom_headers, custom_cookies, timeout)
            results.append(result)
        return results

    # Threaded processing (original behavior) - no rate limiting in threaded mode
    results = []
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        # Submit all tasks
        future_to_url = {
            executor.submit(warm_url, url, custom_headers, custom_cookies, timeout): url
            for url in urls
        }
        # Collect results as they complete
        for future in future_to_url:
            result = future.result()
            results.append(result)
    return results


def load_config_from_json(json_file):
    """Load configuration from JSON file"""
    try:
        with open(json_file, "r") as f:
            config_data = json.load(f)

        # Validate the structure of the loaded configuration
        if not isinstance(config_data, list):
            raise ValueError("JSON configuration must be a list of configurations")

        for i, config in enumerate(config_data):
            if "name" not in config or "headers" not in config:
                raise ValueError(
                    f"Configuration at index {i} is missing required fields 'name' and/or 'headers'"
                )

        return config_data
    except FileNotFoundError:
        print(f"Error: Configuration file '{json_file}' not found.")
        sys.exit(1)
    except json.JSONDecodeError as e:
        print(f"Error: Invalid JSON in configuration file: {e}")
        sys.exit(1)
    except Exception as e:
        print(f"Error loading configuration from JSON: {e}")
        sys.exit(1)


def extract_base_url_from_config(config):
    """Extract base URL from configuration (removing protocol and trailing slashes)"""
    website_base_url = config.get("website_base_url", "")
    if not website_base_url:
        return ""

    # Remove protocol (http:// or https://)
    if website_base_url.startswith("http://"):
        base_url = website_base_url[7:]
    elif website_base_url.startswith("https://"):
        base_url = website_base_url[8:]
    else:
        base_url = website_base_url

    # Remove trailing slashes
    base_url = base_url.rstrip("/")

    # If there's a path after the domain, remove it to get just the domain + port
    if "/" in base_url:
        # Find where the domain ends and path begins (before any slash)
        parts = base_url.split("/", 1)
        base_url = parts[0]  # Take only the part before the first slash

    return base_url


def extract_base_url_from_url(url):
    """Extract base URL from full URL (removing protocol and trailing slashes)"""
    import urllib.parse

    try:
        parsed = urllib.parse.urlparse(url)
        if parsed.netloc:
            # Reconstruct the base URL without protocol
            base_url = f"{parsed.netloc}"
            return base_url
    except Exception:
        pass
    return None


def match_urls_by_base_url(urls, config):
    """Match URLs by their base URL against configuration's website_base_url"""
    # Get the base URL from configuration
    config_base_url = extract_base_url_from_config(config)

    if not config_base_url:
        return urls  # Return all URLs if no base URL specified

    matched_urls = []
    for url in urls:
        url_base = extract_base_url_from_url(url)
        if url_base and url_base == config_base_url:
            matched_urls.append(url)

    return matched_urls


def main():
    parser = argparse.ArgumentParser(description="URL Cache Warmer")
    parser.add_argument(
        "--files", nargs="+", required=True, help="CSV files containing URLs to warm up"
    )
    parser.add_argument(
        "--threads", type=int, default=5, help="Number of threads to use (default: 5)"
    )
    parser.add_argument(
        "--timeout",
        type=int,
        default=50,
        help="Timeout for each request in seconds (default: 50)",
    )
    parser.add_argument(
        "--json-config",
        help="Path to JSON configuration file with warming configurations",
        required=True,
    )
    parser.add_argument(
        "--without-async",
        type=int,
        default=0,
        help="Run without async (threads) processing. Set to 1 to disable threading (default: 0)",
    )
    parser.add_argument(
        "--rate-limit",
        type=int,
        default=None,
        help="Rate limit for URL warming in URLs per minute (e.g., --rate-limit 100 for 100 URLs/minute)",
    )

    args = parser.parse_args()

    # Validate threads parameter
    if args.threads <= 0:
        print("Error: Threads must be a positive integer")
        sys.exit(1)

    all_urls = []
    for csv_file in args.files:
        print(f"Reading URLs from {csv_file}.")
        urls = read_urls_from_csv(csv_file)
        if not urls:
            print(f"No URLs found in {csv_file}.")
            continue
        print(f"Found {len(urls)} URLs in {csv_file}.")
        all_urls.extend(urls)

    if not all_urls:
        print("No URLs found in any of the provided files.")
        return

    print(f"Total URLs to warm up: {len(all_urls)}")

    # Load configurations from JSON or use default hardcoded ones
    if args.json_config:
        configurations = load_config_from_json(args.json_config)
        print(f"Loaded {len(configurations)} configurations from JSON file")
    else:
        print("Error: no configurations provided")
        return

    # Run warmup for each configuration with matching URLs only
    total_hit_count = 0
    total_miss_count = 0

    for i, config in enumerate(configurations):
        print(f"\n{'=' * 50}")
        print(f"STAGE {i + 1}: {config['name']} Configuration")
        print(f"{'=' * 50}")

        # Filter URLs that match the current configuration's website_base_url
        urls_to_warm = match_urls_by_base_url(all_urls, config)

        if not urls_to_warm:
            print("No matching URLs found for this configuration.")
            continue

        print(f"Configuration '{config['name']}' will warm up {len(urls_to_warm)} URLs")

        print("Starting to warm up URLs...")

        # Warm up URLs in parallel
        start_time = time.time()

        # Process URLs in parallel
        results = process_urls_threaded(
            urls_to_warm,
            max_workers=args.threads,
            timeout=args.timeout,
            custom_headers=config["headers"],
            custom_cookies=config.get("cookies", {}),
            use_threads=(args.without_async == 0),
            rate_limit=args.rate_limit,
        )

        # Count hits and misses
        hit_count = 0
        miss_count = 0
        for result in results:
            if result["success"] and result["x_cache"]:
                x_cache = result["x_cache"]
                if "HIT" in x_cache.upper():
                    hit_count += 1
                elif "MISS" in x_cache.upper():
                    miss_count += 1

        # Print summary with colored output
        end_time = time.time()

        print("-" * 50)
        print("FINAL SUMMARY:")
        print(f"Total URLs processed: {len(urls_to_warm)}")
        print(f"Cache HIT: {hit_count}")
        print(f"Cache MISS: {miss_count}")
        print(f"Total time: {end_time - start_time:.2f} seconds")

        total_hit_count += hit_count
        total_miss_count += miss_count

    # Print final summary
    print(f"\n{'=' * 50}")
    print("FINAL AGGREGATE SUMMARY:")
    print(f"{'=' * 50}")
    print(f"Total URLs processed: {len(all_urls)} (across all configurations)")
    print(f"Total Cache HIT: {total_hit_count}")
    print(f"Total Cache MISS: {total_miss_count}")


if __name__ == "__main__":
    main()
