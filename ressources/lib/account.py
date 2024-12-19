# vim: tabstop=4 autoindent expandtab
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
import datetime


class Account:

    logger = logging.getLogger("ACCOUNT")
    accounts = {}

    # ======= Méthodes statiques =======
    # ==================================

    @staticmethod
    def all():
        return __class__.accounts.values()

    @staticmethod
    def byId(id):
        if id in __class__.accounts:
            return __class__.accounts[id]
        return None

    # ====== Méthodes d'instance =======
    # ==================================

    def __init__(self, id, name, accessToken, expiresAt, expiresIn):
        self.setId(id)
        self.setName(name)
        self.setAccessToken(accessToken)
        self.setExpiresAt(expiresAt)
        self.setLifetime(expiresIn)
        self.accounts[id] = self
        self.logger = logging.getLogger(f"[{name}]")
        self.logger.addFilter(logFilter())
        logFilter.add_sensible(accessToken)

    def __del__(self):
        self.logger.debug(f"del account {self.getId()}")
        if self.getId() in self.accounts:
            del self.accounts[self.getId()]

    def getTime2renew(self):
        return self.getExpiresAt() - self.getLifetime() / 2

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

    # ExpiresIn
    #
    def getExpiresIn(self):
        return self._expiresIn

    def setExpiresIn(self, expiresIn):
        self._expiresIn = expiresIn

    # Lifetime
    #
    def getLifetime(self):
        return self._lifetime

    def setLifetime(self, expiresIn):
        self._lifetime = expiresIn

    # Id
    #
    def getId(self):
        return self._id

    def setId(self, id):
        self._id = id

    # Name
    #
    def getName(self):
        return self._name

    def setName(self, name):
        self._name = name

