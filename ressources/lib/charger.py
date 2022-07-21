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
import os
import configparser
from signalrcore.hub_connection_builder import HubConnectionBuilder
import signalrcore.helpers
from jeedom import *

class Charger():

    _chargers = {}
    _translate = None
    _mapping = configparser.ConfigParser()
    _mapping.read(os.path.dirname(__file__) + '/../../core/config/mapping.ini')
    _transforms = configparser.ConfigParser()
    _transforms.read(os.path.dirname(__file__) + '/../../core/config/transforms.ini')
    logger = logging.getLogger('CHARGER')

    # ======= Methodes statiques =======
    # ==================================

    @classmethod
    def all(cls):
        return cls._chargers.values()

    @classmethod
    def byId(cls,id):
        if id in cls._chargers:
            return cls._chargers[id]
        return None

    @classmethod
    def set_jeedom_com(cls,jeedom_com):
        cls._jeedom_com = jeedom_com

    @classmethod
    def logicalIds(cls,cmdId):
        if cmdId not in cls._mapping['signalR']:
            return []
        cmdApi = cls._mapping['signalR'][cmdId]
        if cmdApi not in cls._mapping['API']:
            cls.logger.warning(f'{cmdApi} not in API commands')
            return []
        logicalIds = cls._mapping['API'][cmdApi].split(',')
        return logicalIds

    @classmethod
    def transforms(cls,logicalId,value):
        if logicalId not in cls._transforms:
            return value
        if value in cls._transforms[logicalId]:
            return cls._transforms[logicalId][value]
        if 'default' in cls._transforms[logicalId]:
            return cls._transforms[logicalId]['default']
        return value
    # =================================

    def __init__(self, id, name, serial, account):
        self._id = id
        self._name = name
        self._serial = serial
        self._account = account
        self._state = 'initialized'
        self._chargers[id] = self
        self._nbRestart = 0
        self.logger = logging.getLogger(f'[{account.getName()}][{serial}]');

    def __del__(self):
        self.logger.debug (f"del charger {self._name}")
        self.connection.close()

    def remove(self):
        self.logger.debug (f"remove charger {self._name}")
        self._state = "closing"
        self.connection.stop()

    def getToken(self):
        return self.getAccount().getAccessToken()

    def run(self):

        self._lastMessage = None
        self._nbRestart = 0
        url = "https://api.easee.cloud/hubs/chargers"
        options = {'access_token_factory': self.getToken}

        self.connection = HubConnectionBuilder()\
                .with_url(url,options)\
                .with_automatic_reconnect({
                    'type': 'raw',
                    'keep_alive_interval': 10,
                    'interval_reconnect': 5,
                    'max_attemps': 5
                    }).build()
        logLevel = jeedom_utils.get_logLevel()
        extendedDebug = jeedom_utils.get_extendedDebug()
        if logLevel == 'debug' and not extendedDebug:
            signalrcore.helpers.Helpers.get_logger().setLevel(logging.INFO)
        self.connection.on_open(lambda: self.on_open())
        self.connection.on_close(lambda: self.on_close())
        self.connection.on_reconnect(lambda: self.on_reconnect())
        self.connection.on_error(lambda data: self.on_error(data))
        self.connection.on('ProductUpdate', self.on_Update)
        self.connection.on('ChargerUpdate', self.on_Update)
        self.connection.on('CommandResponse', self.on_CommandResponse)
        self._state = 'connecting'
        self.connection.start()
        return

    def is_running(self):
        if self.connection == None:
            return False
        return self.connection.transport.is_running()

    def on_open(self):
        self.logger.debug(f'openning connection {self.getSerial()}')
        self.connection.send("SubscribeWithCurrentState", [self.getSerial(), True])
        self._state = 'connected'
        return

    def on_close(self):
        self._state = 'disconnected'
        self.logger.debug(f'Closed connection {self.getSerial()}')
        if self._id in self._chargers:
            del self._chargers[self._id]

    def on_reconnect(self):
        self.logger.warning(f'reconnecting {self.getSerial()}')
        self._nbRestart += 1

    def on_error(self,data):
        self.logger.error(data.error)
        self.logger.error(data)

    def on_Update(self,messages):
        for message in messages:
            if message == self._lastMessage:
                continue
            self._lastMessage = message
            cmdId = str(message['id'])
            self.logger.debug(f'Processing command {cmdId}, value: {message["value"]}')
            if cmdId not in self._mapping['signalR']:
                continue
            for logicalId in self.logicalIds(cmdId):
                value = self.transforms(logicalId,message['value'])
                self.logger.debug(f"  - {logicalId} : {value}")
                self._jeedom_com.send_change_immediate({
                    'object' : 'cmd',
                    'charger' : self.getId(),
                    'logicalId' : logicalId,
                    'value' : value
                })

    def on_CommandResponse(self,massages):
        pass

    # ======== setter / getter ========
    # =================================

    def getAccount(self):
        return (self._account)

    def getId(self):
        return (self._id)

    def getName(self):
        return (self._name)

    def getNbRestart(self):
        return (self._nbRestart)

    def getSerial(self):
        return (self._serial)

    def getState(self):
        return self._state
