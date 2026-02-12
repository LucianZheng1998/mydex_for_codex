#!/usr/bin/env python3

import base64
import hashlib
import json
import requests
import urllib.parse

# Constants
MYDEX_PDS_PATH = "https://sbx-api.mydex.org"
CONNECTION_NID = "45678"
CONNECTION_TOKEN = "abcdefghijklmnopqrstuvwxyz123456789"

OAUTH_TOKEN_ENDPOINT = "https://sbx-op.mydexid.org/oauth2/token"
OAUTH_CLIENT_ID = "abcd1234-abcd-1234-abcd-123456abcdef"
OAUTH_CLIENT_SECRET = "CHANGEME"
OAUTH_SCOPE = "mydex:pdx"

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

def identify():
    """
    Request new URL for Identifying the mydexid (as a precursor to
    determining if you need to send them on FTC or not)
    """
    request_data = {
        "connection_nid": CONNECTION_NID,
        "connection_token_hash": hashlib.sha512(CONNECTION_TOKEN.encode()).hexdigest(),
        "return_to": "https://example.com",
        # If you need to link the Member's MydexID and PDS to your own member object,
        # send 'linking_token' as a parameter which represents a unique attribute
        # of your own member or data object in your backend. This will be sent back
        # in the POST callback to your API along with the mydexid.
    }

    # Get OAuth access token
    oauth_access_token = get_oauth2_access_token()

    # POST to the identify endpoint
    url = f"{MYDEX_PDS_PATH}/identify"
    headers = {
        'Authorization': f"Bearer {oauth_access_token}",
        'Content-Type': 'application/json'
    }

    response = requests.post(url, headers=headers, data=json.dumps(request_data))
    response.raise_for_status()

    print(response.json())

if __name__ == "__main__":
    identify()
