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

class Account():

    _accounts = {}

    # ======= Méthodes statiques =======
    # ==================================

    @staticmethod
    def all():
        return __class__._accounts.values()

    @staticmethod
    def byName(name):
        if name in __class__._accounts:
            return __class__._accounts[name]
        return None

    # ====== Méthodes de logging =======
    # ==================================

    def log_debug(self,txt):
        logging.debug(f'[account][{self._name}] {txt}')

    def log_info(self,txt):
        logging.info(f'[account][{self._name}] {txt}')

    def log_warning(self,txt):
        logging.warning(f'[account][{self._name}] {txt}')

    def log_error(self,txt):
        logging.error(f'[account][{self._name}] {txt}')

    # ====== Méthodes d'instance =======
    # ==================================

    def __init__(self, name, accessToken, refreshToken, expiresAt, expiresIn):
        self.setName(name)
        self.setAccessToken(accessToken)
        self.setRefreshToken(refreshToken)
        self.setExpiresAt(expiresAt)
        self.setLifetime(expiresIn)
        self._accounts[name] = self

    def __del__(self):
        self.log_debug (f"del account {self.getName()}")
        if self.getName() in self._accounts:
            del self._accounts[self.getName()]

    def remove(self):
        self.log_debug (f"remove account {self.getName()}")
        if self.getName() in self._accounts:
            del self._accounts[self.getName()]

    def getTime2renew(self):
        return self.getExpiresAt() - self.getLifetime()/2
    
    def refreshToken(self):
        self.log_debug("'refreshToken' is called")
        if time.time() > self.getTime2renew():
            self.log_debug("Token need a refresh")
            url = "https://api.easee.cloud/api/accounts/refresh_token"
            headers = {
                    "Accept": "application/json",
                    "Content-Type": "application/*+json",
                    "Authorization": f"Bearer {self._accessToken}"
                    }
            payload = {
                    "accessToken": self.getAccessToken(),
                    "refreshToken": self.getRefreshToken()
                    }
            try:
                response = requests.post(url, data=json.dumps(payload), headers=headers)
                if response.status_code != requests.codes['ok']:
                    self.log_warning("Error refreshing Token: return code " + str(response.status_code))
                    return
                self.log_info(response.text)
                tok = json.loads(response.text)
                self.setAccessToken(tok['accessToken'])
                self.setRefreshToken(tok['refreshToken'])
                self.setExpiresAt(time.time() + tok['expiresIn'])
                self.setLifetime(tok['expiresIn'])
    
            except Exception as error:
                self.log_warning("Error refreshing Token: " + str(error))

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

    def setName(self,name):
        self._name = name

    # RefreshToken
    #
    def getRefreshToken(self):
        return self._refreshToken

    def setRefreshToken(self, refreshToken):
        self._refreshToken = refreshToken

