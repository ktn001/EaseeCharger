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
from jeedom import *

class Charger():

    _chargers = {}
    _translate = None
    _mapping = configparser.ConfigParser()
    _mapping.read(os.path.dirname(__file__) + '/../../core/config/mapping.ini')
    _transforms = configparser.ConfigParser()
    _transforms.read(os.path.dirname(__file__) + '/../../core/config/transforms.ini')

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
    def LogicIds(cls,cmdId):
        cmdApi = mapping['signalR-API'][cmdid]
        logicalIds = mapping['API'][cmdApi].split(',')
        return logicalIds

    # ====== Methodes de logging ======
    # =================================

    def log_debug(self,txt):
        logging.debug(f'[charger][{self._serial}]   {txt}')

    def log_info(self,txt):
        logging.info(f'[charger][{self._serial}]   {txt}')

    def log_warning(self,txt):
        logging.warning(f'[charger][{self._serial}]   {txt}')

    def log_error(self,txt):
        logging.error(f'[charger][{self._serial}]   {txt}')

    # ====== Methodes d'instance ======
    # =================================

    def __init__(self, id, name, serial, account):
        self._id = id
        self._name = name
        self._serial = serial
        self._account = account
        self._state = 'initialized'
        self._chargers[id] = self
        self._nbRestart = 0

    def __del__(self):
        self.log_debug (f"del charger {self._name}")
        if self._id in self._chargers:
            del self._chargers[self._id]

    def remove(self):
        self.log_debug (f"remove charger {self._name}")
        if self._id in self._chargers:
            del self._chargers[self._id]

    def getToken(self):
        return self.getAccount().getAccessToken()

    def run(self):
        logLevel = jeedom_utils.get_logLevel()
        extendedDebug = jeedom_utils.get_extendedDebug()

        if logLevel == 'error':
            logLevel = logging.ERROR
        elif logLevel =='warning':
            logLevel = logging.WARNING
        elif logLevel == 'info':
            logLevel = logging.INFO
        elif logLevel == 'debug':
            if extendedDebug:
                logLevel= logging.DEBUG
            else:
                logLevel = logging.INFO

        self._lastMessage = None
        self._nbRestart = 0
        url = "https://api.easee.cloud/hubs/chargers"
        options = {'access_token_factory': self.getToken}

        self.connection = HubConnectionBuilder()\
                .with_url(url,options)\
                .configure_logging(logLevel)\
                .with_automatic_reconnect({
                    'type': 'raw',
                    'keep_alive_interval': 10,
                    'interval_reconnect': 5,
                    'max_attemps': 5
                    }).build()
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

    def on_open(self):
        self.log_debug(f'openning connection {self.getSerial()}')
        self.connection.send("SubscribeWithCurrentState", [self.getSerial(), True])
        self._state = 'connected'
        return

    def on_close(self):
        self._state = 'disconnected'
        self.log_debug(f'Closing connection {self.getSerial()}')

    def on_reconnect(self):
        self.log_warning(f'reconnecting {self.getSerial()}')
        self._nbRestart += 1 

    def on_error(self,data):
        self.log_error(data.error)
        self.log_error(data)

    def on_Update(self,messages):
        for message in messages:
            if message == self._lastMessage:
                continue
            self._lastMessage = message
            cmdId = str(message['id'])
            self.log_debug(f'Processing command {cmdId}, value: {message["value"]}')
            if cmdId not in self._mapping['signalR']:
                continue
            for logicalId in self.logicalIds(cmdId]):
                value = self._transforms.get(logicalId,message['value'],fallback=message['value'])
                self.log_debug(f"  - {logicalId} : {value}")
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
