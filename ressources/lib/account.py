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
import json
from logfilter import *
import datetime
import asyncio
import pysignalr
from pysignalr.client import SignalRClient
from pysignalr.exceptions import AuthorizationError
from pysignalr.messages import CompletionMessage
import websockets.asyncio.client

class Account:

    logger = logging.getLogger("ACCOUNT")
    accounts = {}
    sr_base = 'https://streams.easee.com/hubs/products'
    _messages_queue = None

    # ======= Méthodes statiques =======
    # ==================================

    @classmethod
    def all(cls):
        return cls.accounts.values()

    @classmethod
    def by_id(cls,id):
        return cls.accounts.get(id, None)

    @classmethod
    def by_charger_id(cls, charger_id):
        for account in cls.all():
            if charger_id in account._chargers:
                return account
        return None

    @classmethod
    def get_messages_queue(cls):
        if cls._messages_queue == None:
            cls._messages_queue = asyncio.Queue()
        return cls._messages_queue

    @classmethod
    def stop_all(cls):
        pass

    # ====== Méthodes d'instance =======
    # ==================================

    def __init__(self, id, name, access_token, expires_at, expires_in):
        self._id = id
        self._name = name
        self._access_token = access_token
        self._expires_at = expires_at
        self._lifetime = expires_in
        self._chargers = {}
        self._state = 'idle'
        self._sr_connection = None
        self._subscriptions = []
        __class__.accounts[id] = self
        self.logger = logging.getLogger(f"[{name}]")
        self.logger.addFilter(logFilter())
        logFilter.add_sensible(access_token)

    def remove(self):
        self.logger.debug(f"del account {self._id}")
        if self._id in __class__.accounts:
            del __class__.accounts[self._id]

    async def register_charger(self, charger_id, charger_serial, charger_name):
        charger = {
            "id": charger_id,
            "serial": charger_serial,
            "name": charger_name
        }
        self._chargers[charger_id] = charger
        if self._state == 'idle':
            await self._start_sr_connection()
        for i in range(20):
            if self._state == 'connected':
                await self._sr_subscription(charger_serial)
                break
            await asyncio.sleep(1)

    async def unregister_charger(self, charger_id):
        if charger_id in self._chargers:
            charger_serial = self._chargers[charger_id]['serial']
            await self._sr_unsubscription(charger_serial)
            del self._chargers[charger_id]
        if not self._chargers and self._state != 'idle':
            await self._sr_close()

    async def _start_sr_connection(self):
        self.get_messages_queue()
        if self._state != 'idle':
            self.logger.debug(f"L'account {self._name} n'est pas <idle>")
            return
        self._sr_task = asyncio.create_task(self._sr_connect_loop(), name=f"signalr {self._name}")

    async def _sr_open_cb(self):
        self.logger.debug(f"SR stream for {self._name} connected")
        self._state = 'connected'

    async def _sr_close_cb(self):
        self.logger.debug(f"SR stream for {self._name} disconnected ou failed to connect")
        self._state = 'idle'
        
    async def _sr_error_cb(self, message):
        self.logger.error(f"SR for {self._name} error: {message.error}")

    async def _sr_product_update_cb(self, data):
        await self._messages_queue.put(data)

    async def _sr_connect_loop(self):
        if self._sr_connection is not None:
            return

        headers = {
            "User-Agent": f"jeedom_EaseeCharger/1.0i SignalR",
            "Accept": "application/octet-stream",
            "Content-Type": "application/json;charset=UTF-8",
            "Authorization": f"Bearer {self._access_token}",
        }
        while True:
            try:
                self.sr_connection = SignalRClient(__class__.sr_base, headers=headers)
                self.sr_connection.on_open(self._sr_open_cb)
                self.sr_connection.on_close(self._sr_close_cb)
                self.sr_connection.on_error(self._sr_error_cb)
                self.sr_connection.on("ProductUpdate", self._sr_product_update_cb)
                await self.sr_connection.run()
            except AuthorizationError as e:
                self.logger.error("SR Authorization Error")
                self._state = 'authorization error'
                await asyncio.sleep(60)
                continue
            except Exception as ex:
                self._state = 'on error'
                self.logger.error("SR for %s exception: %s: %s. Retry in %d seconds", self._name, type(ex).__name__, ex, 60)
                await asyncio.sleep(60)
                continue
            except asyncio.CancelledError:
                self.logger.debug("SR task cancelled (self)")
            break
        self._state = "connected"

    async def _sr_subscription(self, charger_serial):
        if charger_serial in self._subscriptions:
            return
        while True:
            if self._state == 'idle':
                return
            if self._state != 'connected':
                self.logger.debug(f"SR for {self._name} is not connected. Retrying in 10 secondes")
                await asyncio.sleep(10)
                continue
            self.logger.info(f"SR for {self._name}: Subscribe to {charger_serial}")
            await self.sr_connection.send("SubscribeWithCurrentState", [charger_serial, True])
            break

    async def _sr_unsubscription(self, charger_serial):
        if charger_serial in self._subscriptions:
            return
        while True:
            if self._state == 'idle':
                return
            if self._state != 'connected':
                self.logger.debug(f"SR for {self._name} is not connected. Retrying in 10 secondes")
                await asyncio.sleep(10)
                continue
            self.logger.info(f"SR for {self._name}: Unsubscribe to {charger_serial}")
            await self.sr_connection.send("UnsubscribeCharger", [charger_serial, True])
            break
        


    async def _sr_close(self):
        if self._sr_task is not None:
            self._sr_task.cancel()
            try:
                await self._sr_task
            except asyncio.CancelledError:
                self.logger.debug(f"SR for {self._name} closed")
        self._sr_task = None
        self._sr_connection = None
        self._state = 'idle'

    def stop_stream(self):
        pass

    # id
    # ==
    @property
    def id(self):
        return self._id

    # name
    # ====
    @property
    def name(self):
        return self._name

    @name.setter
    def name(self, name):
        self._name = name

    # expire_at
    # =========
    @property
    def expire_at(self):
        return self._expires_at

    @expire_at.setter
    def expires_at(self, expires_at):
        self._expires_at = expires_at

    # lifetime
    # ========
    @property
    def lifetime(self):
        return self._lifetime

    @lifetime.setter
    def lifetime(self, lifetime):
        self._lifetime = lifetime

    # time_to_renew
    # =============
    @property
    def time_to_renew(self):
        return self._expires_at - self._lifetime / 2

    # chargers
    # ========
    @property
    def chargers(self):
        return self._chargers

    @property
    def charger(self, id):
        if id in self._chargers:
           return self._chargers[id]
        return None

    # state
    # =====
    @property
    def state(self):
        return self._state
