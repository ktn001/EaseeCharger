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
import threading
from signalrcore.hub.errors import UnAuthorizedHubError
from signalrcore.hub_connection_builder import HubConnectionBuilder
from logfilter import *
from jeedom import *


class Charger():

    chargers = {}
    mapping = configparser.ConfigParser()
    mapping.read(os.path.dirname(__file__) + '/../../core/config/mapping.ini')
    transforms = configparser.ConfigParser()
    transforms.read(os.path.dirname(__file__) + '/../../core/config/transforms.ini')
    logger = logging.getLogger('CHARGER')
    logger.addFilter(logFilter())

    # ======= Methodes statiques =======
    # ==================================

    @classmethod
    def all(cls):
        return cls.chargers.values()

    @classmethod
    def byId(cls,id):
        if id in cls.chargers:
            return cls.chargers[id]
        return None

    @classmethod
    def set_jeedom_com(cls,jeedom_com):
        cls.jeedom_com = jeedom_com

    @classmethod
    def logicalIds(cls,cmdId):
        if cmdId not in cls.mapping['signalR']:
            return []
        cmdApi = cls.mapping['signalR'][cmdId]
        if cmdApi not in cls.mapping['API']:
            cls.logger.warning(f'{cmdApi} not in API commands')
            return []
        logicalIds = cls.mapping['API'][cmdApi].split(',')
        return logicalIds

    @classmethod
    def value_for_logicalId(cls,logicalId,value):
        if logicalId not in cls.transforms:
            return value
        if value in cls.transforms[logicalId]:
            return cls.transforms[logicalId][value]
        if 'default' in cls.transforms[logicalId]:
            return cls.transforms[logicalId]['default']
        return value
    # =================================

    def __init__(self, id, name, serial, account):
        self.id = id
        self.name = name
        self.serial = serial
        self.account = account
        self.state = 'initialized'
        self.chargers[id] = self
        self.nbSignalrRestart = 0
        self.nbWatcherRestart = 0
        self.logger = logging.getLogger(f'[{account.getName()}][{serial}]');
        filters = self.logger.filters
        for lf in filters:
            self.logger.removeFilter(lf)
        self.logger.addFilter(logFilter())
        self.connection = None

    def __del__(self):
        self.logger.debug (f"del charger {self.name}")
        self.connection.close()

    def remove(self):
        self.logger.debug (f"remove charger {self.name}")
        self.state = "closing"
        self.connection.stop()

    def getToken(self):
        return self.getAccount().getAccessToken()

    def run(self):
        self.lastMessage = None
        self.nbSignalrRestart = 0
        self.nbWatcherRestart = 0
        url = "https://api.easee.com/hubs/chargers"
        options = {'access_token_factory': self.getToken}

        self.connection = HubConnectionBuilder()\
                .with_url(url,options)\
                .with_automatic_reconnect({
                    'type': 'raw',
                    'keep_alive_interval': 10,
                    'interval_reconnect': 5,
                    'max_attemps': 5
                    }).build()
        self.connection.on_open(lambda: self.on_open())
        self.connection.on_close(self.on_close)
        self.connection.on_reconnect(lambda: self.on_reconnect())
        self.connection.on_error(lambda data: self.on_error(data))
        self.connection.on('ProductUpdate', self.on_Update)
        self.connection.on('ChargerUpdate', self.on_Update)
        self.connection.on('CommandResponse', self.on_CommandResponse)
        try:
            self.state = 'connecting'
            self.connection.start()
            self.watcher = threading.Thread(target=self.watch_connection)
            self.watcher.start()
            return True
        except UnAuthorizedHubError as error:
            self.state = 'error'
            self.logger._error("login Error")
            self.connection = None
            return False

    def watch_connection (self):
        while 1:
            if self.state == 'closing' or self.state == 'disconnected':
                return
            if self.state == 'connected':
                if not self.is_running():
                    self.logger.warning("Le watcher redémarre la connection")
                    self.connection.start()
                    self.nbWatcherRestart += 1
            time.sleep (5)

    def is_running(self):
        if self.connection == None:
            return False
        return self.connection.transport.is_running()

    def on_open(self):
        self.logger.debug(f'openning connection {self.getSerial()}')
        self.connection.send("SubscribeWithCurrentState", [self.getSerial(), True])
        self.state = 'connected'
        return

    def on_close(self):
        if self.state != "closing":
            self.logger.debug(f"on_close called but state is {self.state}")
            return
        self.state = 'disconnected'
        self.logger.debug(f'Closed connection {self.getSerial()}')
        if self.id in self.chargers:
            del self.chargers[self.id]

    def on_reconnect(self):
        self.logger.warning(f'reconnecting {self.getSerial()}')
        self.nbSignalrRestart += 1

    def on_error(self,data):
        self.logger.error(data.error)
        self.logger.error(data)

    def on_Update(self,messages):
        for message in messages:
            if message == self.lastMessage:
                continue
            self.lastMessage = message
            cmdId = str(message['id'])
            self.logger.debug(f'Processing command {cmdId}, value: {message["value"]}')
            if cmdId not in self.mapping['signalR']:
                continue
            for logicalId in self.logicalIds(cmdId):
                value = self.value_for_logicalId(logicalId,message['value'])
                self.logger.debug(f"  - {logicalId} : {value}")
                self.jeedom_com.send_change_immediate({
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
        return (self.account)

    def getId(self):
        return (self.id)

    def getName(self):
        return (self.name)

    def getNbSignalrRestart(self):
        return (self.nbSignalrRestart)

    def getNbWatcherRestart(self):
        return (self.nbWatcherRestart)

    def getSerial(self):
        return (self.serial)

    def getState(self):
        return self.state
