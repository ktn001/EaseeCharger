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

    def __init__(self, name, accessToken, expiresAt, expiresIn):
        self._name = name
        self._accessToken = accessToken
        self._expiresAt = expiresAt
        self._lifetime = expiresIn
        self._accounts[name] = self

    def __del__(self):
        self.log_debug (f"del account {self._name}")
        if self._name in self._accounts:
            del self._accounts[self._name]

    def remove(self):
        self.log_debug (f"remove account {self._name}")
        if self._name in self._accounts:
            del self._accounts[self._name]

    def setAccessToken(self, accessToken, expiresAt):
        self._accessToken = accessToken
        self._expiresAt = expiresAt
        return self

    # ======== getter / setter =========
    # ==================================

    def getAccessToken(self):
        return self._accessToken

    def getExpiresAt(self):
        return self._expiresAt

    def getLifetime(self):
        return self._lifetime

    def getName(self):
        return self._name

    def getTime2renew(self):
        return self._expiresAt - self._lifetime/2
