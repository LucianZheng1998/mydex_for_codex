#!/usr/bin/env python3
import base64
import json
import sys
import urllib.parse
import requests

# ---- Constants ----
OAUTH_TOKEN_ENDPOINT = "https://op.mydexid.org/oauth2/token"
OAUTH_MRD_CLIENT_ID = "abcd1234-abcd-1234-abcd-123456abcdef"
OAUTH_MRD_CLIENT_SECRET = "CHANGEME"
OAUTH_MRD_SCOPES = "countries"
OAUTH_MRD_GRANT_TYPE = "client_credentials"
MRD_API_ENDPOINT = "https://api-mrd.mydex.org/countries/FR"

# ---- Helpers ----
def _basic_auth_header(client_id: str, client_secret: str) -> str:
    """
    Build a Basic auth header where id/secret are URL-encoded prior to base64
    """
    cid = urllib.parse.quote(client_id, safe="")
    csec = urllib.parse.quote(client_secret, safe="")
    token = base64.b64encode(f"{cid}:{csec}".encode("utf-8")).decode("ascii")
    return f"Basic {token}"

# ---- Core calls ----
def request_token() -> str | None:
    """Request an OAuth2 access token. Returns the token string or None."""
    headers = {
        "Content-Type": "application/x-www-form-urlencoded",
        "Authorization": _basic_auth_header(OAUTH_MRD_CLIENT_ID, OAUTH_MRD_CLIENT_SECRET),
    }
    data = {
        "grant_type": OAUTH_MRD_GRANT_TYPE,
        "scope": OAUTH_MRD_SCOPES,
    }

    try:
        resp = requests.post(OAUTH_TOKEN_ENDPOINT, headers=headers, data=data, timeout=20)
        resp.raise_for_status()
    except requests.RequestException as e:
        print(f"Token request failed: {e}", file=sys.stderr)
        return None

    try:
        payload = resp.json()
    except json.JSONDecodeError:
        print(f"Token response was not JSON: {resp.text}", file=sys.stderr)
        return None

    access_token = payload.get("access_token")
    if not access_token:
        print(f"No access_token in response:\n{json.dumps(payload, indent=2)}", file=sys.stderr)
        return None
    return access_token


def request_country(token: str) -> None:
    """
    Call the MRD endpoint for France.
    Prints the JSON response.
    """
    headers = {
        "Authorization": f"Bearer {token}",
        "X-Mrd-Scopes": OAUTH_MRD_SCOPES,
    }

    try:
        resp = requests.get(MRD_API_ENDPOINT, headers=headers, timeout=20)
        resp.raise_for_status()
    except requests.RequestException as e:
        print(f"MRD request failed: {e}", file=sys.stderr)
        return

    try:
        data = resp.json()
    except json.JSONDecodeError:
        print(resp.text)
        return

    # Pretty-print JSON
    print(json.dumps(data, indent=2, ensure_ascii=False))


# ---- Run ----
if __name__ == "__main__":
    print("Requesting a token")
    token = request_token()
    if token:
        request_country(token)

