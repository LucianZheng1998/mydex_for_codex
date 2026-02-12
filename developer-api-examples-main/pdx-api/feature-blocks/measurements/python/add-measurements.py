#!/usr/bin/env python3

import base64
import hashlib
import json
import requests
import urllib.parse

# Constants
MYDEX_PDS_PATH = "https://sbx-api.mydex.org"
MEMBER_UID = "1234"
MEMBER_KEY = "ABCDEFGHIJKLMNOP123456789"
CONNECTION_ID = "1234-45678"

OAUTH_TOKEN_ENDPOINT = "https://sbx-op.mydexid.org/oauth2/token"
OAUTH_CLIENT_ID = "abcd1234-abcd-1234-abcd-123456abcdef"
OAUTH_CLIENT_SECRET = "CHANGEME"
OAUTH_SCOPE = "mydex:pdx post:/api/pds/add-measurements"

def get_oauth2_access_token():
    """
    Get an OAuth2.0 access token from Mydex's OAuth2.0 server.
    """
    post_data = {
        'grant_type': 'client_credentials',
        'scope': OAUTH_SCOPE
    }

    # Basic Auth Header
    client_id_enc = urllib.parse.quote(OAUTH_CLIENT_ID, safe='')
    client_secret_enc = urllib.parse.quote(OAUTH_CLIENT_SECRET, safe='')
    basic_auth = base64.b64encode(f"{client_id_enc}:{client_secret_enc}".encode()).decode()

    headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Authorization': f"Basic {basic_auth}"
    }

    response = requests.post(OAUTH_TOKEN_ENDPOINT, headers=headers, data=post_data)
    response.raise_for_status()
    return response.json()['access_token']

def add_measurements():
    """
    Add measurement data
    """
    request_data = [{
        "source_device_type": "Mobile",
        "source_name": "Apple Health Kit",
        "measurement_type": "Weight",
        "measurement_timestamp_start": 1648633780,
        "measurement_timestamp_end": 1648633780,
        "measurement_value": 175.8
    }]

    query_params = {
        'uid': MEMBER_UID,
        'con_id': CONNECTION_ID,
    }

    # Get OAuth access token
    oauth_access_token = get_oauth2_access_token()

    # POST to the add-measurements endpoint
    url = f"{MYDEX_PDS_PATH}/api/pds/add-measurements"
    headers = {
        'Authorization': f"Bearer {oauth_access_token}",
        'Content-Type': 'application/json',
        'Connection-Token': MEMBER_KEY,
    }

    response = requests.post(url, headers=headers, params=query_params, data=json.dumps(request_data))
    response.raise_for_status()

    print(response.json())

if __name__ == "__main__":
    add_measurements()
