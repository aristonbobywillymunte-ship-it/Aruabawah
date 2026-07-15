#!/usr/bin/env python3
import ipaddress
import json
import sys
from typing import Optional
from urllib.parse import urlparse

from googlenewsdecoder import gnewsdecoder


MAX_URL_LENGTH = 16 * 1024
MAX_SEGMENT_LENGTH = 12 * 1024
ALLOWED_PATH_PREFIXES = ("/rss/articles/", "/articles/", "/read/")
REJECT_PATH_FRAGMENTS = (
    "/search",
    "/tag",
    "/kategori",
    "/category",
    "/rss",
    "/feed",
)


def emit(success: bool, original_url: Optional[str], error: Optional[str]) -> None:
    print(
        json.dumps(
            {
                "success": success,
                "original_url": original_url,
                "method": "googlenewsdecoder_0.1.7",
                "error": error,
            },
            ensure_ascii=False,
        )
    )


def validate_google_url(raw_url: str) -> Optional[str]:
    if len(raw_url) > MAX_URL_LENGTH:
        return "Google News URL exceeds maximum length"

    parsed = urlparse(raw_url)
    if parsed.scheme != "https":
        return "Google News URL must use https"

    if parsed.hostname != "news.google.com":
        return "Host must be news.google.com"

    if not any(parsed.path.startswith(prefix) for prefix in ALLOWED_PATH_PREFIXES):
        return "Google News path is not allowed"

    for segment in parsed.path.split("/"):
        if len(segment) > MAX_SEGMENT_LENGTH:
            return "Google News path segment exceeds maximum length"

    return None


def validate_decoded_url(decoded_url: str) -> Optional[str]:
    if not decoded_url:
        return "Decoder returned empty URL"

    parsed = urlparse(decoded_url)

    if parsed.scheme not in ("http", "https"):
        return "Decoded URL must use http or https"

    if parsed.username or parsed.password:
        return "Decoded URL must not contain credentials"

    host = (parsed.hostname or "").strip().lower()
    if not host:
        return "Decoded URL host is missing"

    if host in {"localhost", "127.0.0.1", "::1"}:
        return "Decoded URL must not target localhost"

    if host.endswith(".google.com") or host == "google.com":
        return "Decoded URL is still a Google URL"

    try:
        ip = ipaddress.ip_address(host)
        if (
            ip.is_private
            or ip.is_loopback
            or ip.is_reserved
            or ip.is_link_local
            or ip.is_multicast
            or ip.is_unspecified
        ):
            return "Decoded URL must not target a private or reserved IP"
    except ValueError:
        pass

    lower_path = (parsed.path or "").lower()
    for fragment in REJECT_PATH_FRAGMENTS:
        if fragment in lower_path:
            return "Decoded URL points to a non-article path"

    return None


def main() -> int:
    raw_url = sys.argv[1].strip() if len(sys.argv) > 1 else sys.stdin.read().strip()

    if not raw_url:
        emit(False, None, "Google News URL is required")
        return 0

    input_error = validate_google_url(raw_url)
    if input_error is not None:
        emit(False, None, input_error)
        return 0

    try:
        result = gnewsdecoder(raw_url, interval=1, proxy=None)
    except Exception as exc:  # pragma: no cover - defensive guard
        emit(False, None, f"Decoder exception: {exc.__class__.__name__}")
        return 0

    if not isinstance(result, dict) or not result.get("status"):
        error_message = "Decoder failed"
        if isinstance(result, dict):
            error_message = str(result.get("message") or result.get("error") or error_message)
        emit(False, None, error_message[:500])
        return 0

    decoded_url = str(result.get("decoded_url") or "").strip()
    decoded_error = validate_decoded_url(decoded_url)
    if decoded_error is not None:
        emit(False, None, decoded_error)
        return 0

    emit(True, decoded_url, None)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
