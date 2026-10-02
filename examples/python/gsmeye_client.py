"""Minimal client for the GSM EYE Reseller API. Python 3.8+, standard library only.

    api = GsmEyeClient("https://YOUR-DOMAIN", "you@example.com", "YOUR_API_KEY")

    print(api.account_info())
    order = api.place_order("6904", imei="356789104512345",
                            fields={"Picture on sign-in page": "https://i.imgur.com/abc123.jpg"})
    print(api.get_order(order["REFERENCEID"]))
"""

import base64
import json
import urllib.error
import urllib.parse
import urllib.request
from xml.sax.saxutils import escape

STATUS_WAITING = 0
STATUS_IN_PROCESS = 1
STATUS_REJECTED = 3
STATUS_SUCCESS = 4


class GsmEyeError(Exception):
    """An API error. `http_status`: 401 bad key, 403 wrong IP, 404 not found, 429 rate limited, 503 maintenance."""

    def __init__(self, message, http_status=0):
        super().__init__(message)
        self.http_status = http_status


def _b64json(value):
    return base64.b64encode(json.dumps(value, ensure_ascii=False).encode("utf-8")).decode("ascii")


def is_final(status):
    return status in (STATUS_SUCCESS, STATUS_REJECTED)


class GsmEyeClient:
    def __init__(self, site_url, username, api_key, timeout=60):
        self.endpoint = site_url.rstrip("/") + "/api/index.php"
        self.username = username
        self.api_key = api_key
        self.timeout = timeout

    def account_info(self):
        return self._call("accountinfo")["SUCCESS"][0]["AccountInfo"]

    def service_list(self):
        """The full catalogue, keyed by group name. Allowed once per 5 minutes."""
        return self._call("imeiservicelist")["SUCCESS"][0]["LIST"]

    def place_order(self, service_id, imei="", fields=None, quantity=1):
        xml = "<PARAMETERS><ID>{}</ID><QNT>{}</QNT>".format(escape(str(service_id)), int(quantity))
        if imei:
            xml += "<IMEI>{}</IMEI>".format(escape(imei))
        if fields:
            xml += "<CUSTOMFIELD>{}</CUSTOMFIELD>".format(_b64json(fields))
        xml += "</PARAMETERS>"
        return self._call("placeimeiorder", xml)["SUCCESS"][0]

    def place_bulk_orders(self, rows):
        """rows: {"A-1001": {"ID": "6904", "IMEI": "35...", "CUSTOMFIELD": {"Field": "value"}}, ...}"""
        return self._call("placeimeiorderbulk", _b64json(rows))["SUCCESS"]

    def get_order(self, order_id):
        xml = "<PARAMETERS><ID>{}</ID></PARAMETERS>".format(escape(str(order_id)))
        return self._call("getimeiorder", xml)["SUCCESS"][0]

    def get_orders(self, order_ids):
        """order_ids: {"A-1001": 98214, ...} -> {"A-1001": {"STATUS": 4, "CODE": "..."}, ...}"""
        rows = {ref: {"ID": str(order_id)} for ref, order_id in order_ids.items()}
        response = self._call("getimeiorderbulk", _b64json(rows))
        response.pop("apiversion", None)
        return {ref: row["SUCCESS"][0] for ref, row in response.items()}

    def _call(self, action, parameters=None):
        form = {
            "username": self.username,
            "apiaccesskey": self.api_key,
            "requestformat": "JSON",
            "action": action,
        }
        if parameters is not None:
            form["parameters"] = parameters

        request = urllib.request.Request(
            self.endpoint,
            data=urllib.parse.urlencode(form).encode("utf-8"),
            headers={"Accept": "application/json"},
        )
        try:
            with urllib.request.urlopen(request, timeout=self.timeout) as response:
                status, body = response.status, response.read()
        except urllib.error.HTTPError as error:
            status, body = error.code, error.read()
        except urllib.error.URLError as error:
            raise GsmEyeError("Connection failed: {}".format(error.reason))

        try:
            data = json.loads(body)
        except ValueError:
            raise GsmEyeError("Unexpected response (HTTP {})".format(status), status)

        if "ERROR" in data:
            raise GsmEyeError(data["ERROR"][0].get("MESSAGE", "Unknown error"), status)
        if status >= 400:
            raise GsmEyeError(data.get("message", "HTTP {}".format(status)), status)
        return data


if __name__ == "__main__":
    import os

    api = GsmEyeClient(os.environ["GSMEYE_URL"], os.environ["GSMEYE_USER"], os.environ["GSMEYE_KEY"])
    print(json.dumps(api.account_info(), indent=2, ensure_ascii=False))
