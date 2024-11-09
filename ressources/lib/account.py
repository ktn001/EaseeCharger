# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
#

import logging
import time
import requests
import json
from logfilter import *


class Account:

    logger = logging.getLogger("ACCOUNT")
    accounts = {}

    # ======= Méthodes statiques =======
    # ==================================

    @staticmethod
    def all():
        return __class__.accounts.values()

    @staticmethod
    def byName(name):
        if name in __class__.accounts:
            return __class__.accounts[name]
        return None

    # ====== Méthodes d'instance =======
    # ==================================

    def __init__(self, name, accessToken, refreshToken, expiresAt, expiresIn):
        self.setName(name)
        self.setAccessToken(accessToken)
        self.setRefreshToken(refreshToken)
        self.setExpiresAt(expiresAt)
        self.setLifetime(expiresIn)
        self.accounts[name] = self
        self.logger = logging.getLogger(f"[{name}]")
        self.logger.addFilter(logFilter())
        logFilter.add_sensible(accessToken)
        logFilter.add_sensible(refreshToken)

    def __del__(self):
        self.logger.debug(f"del account {self.getName()}")
        if self.getName() in self.accounts:
            del self.accounts[self.getName()]

    def remove(self):
        self.logger.debug(f"remove account {self.getName()}")
        if self.getName() in self.accounts:
            del self.accounts[self.getName()]

    def getTime2renew(self):
        return self.getExpiresAt() - self.getLifetime() / 2

    def refreshToken(self):
        self.logger.debug("'refreshToken' is called")
        if time.time() > self.getTime2renew():
            self.logger.debug("Token need a refresh")
            url = "https://api.easee.com/api/accounts/refresh_token"
            headers = {
                "Accept": "application/json",
                "Content-Type": "application/*+json",
                "Authorization": f"Bearer {self.getAccessToken()}",
            }
            payload = {
                "accessToken": self.getAccessToken(),
                "refreshToken": self.getRefreshToken(),
            }
            try:
                response = requests.post(url, data=json.dumps(payload), headers=headers)
                if response.status_code != requests.codes["ok"]:
                    self.logger.warning(
                        "Error refreshing Token: return code "
                        + str(response.status_code)
                    )
                    return
                tok = json.loads(response.text)
                self.setAccessToken(tok["accessToken"])
                self.setRefreshToken(tok["refreshToken"])
                self.setExpiresAt(time.time() + tok["expiresIn"])
                self.setLifetime(tok["expiresIn"])

            except Exception as error:
                self.logger.warning("Error refreshing Token: " + str(error))

    # ======== getter / setter =========
    # ==================================

    # AccessToken
    #
    def getAccessToken(self):
        return self._accessToken

    def setAccessToken(self, accessToken):
        self._accessToken = accessToken

    # ExpiresAt
    #
    def getExpiresAt(self):
        return self._expiresAt

    def setExpiresAt(self, expiresAt):
        self._expiresAt = expiresAt

    # Lifetime
    #
    def getLifetime(self):
        return self._lifetime

    def setLifetime(self, expiresIn):
        self._lifetime = expiresIn

    # Name
    #
    def getName(self):
        return self._name

    def setName(self, name):
        self._name = name

    # RefreshToken
    #
    def getRefreshToken(self):
        return self._refreshToken

    def setRefreshToken(self, refreshToken):
        self._refreshToken = refreshToken
