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
import signalrcore.helpers
import re
import sys

class logFilter(logging.Filter):
    secure = True
    quietDebug = True
    sensibles = []
    message_to_drop = [
            'Raw message incomming: ',
            ]
    pattern_to_drop = [
            '^Sending message <signalrcore.messages.ping_message.PingMessage',
            '^(Message received)?{"type":\s?6}'
            ]

    @classmethod
    def set_secure(cls, secure):
        cls.secure = secure

    @classmethod
    def get_quietDebug(cls):
        return cls.quietDebug

    @classmethod
    def set_quietDebug(cls, quiet):
        cls.quietDebug = quiet

    @classmethod
    def add_sensible(cls, sensible):
        if not sensible in cls.sensibles:
            cls.sensibles.append(sensible)
    
    @classmethod
    def filter(cls, record):
        if not record.msg:
            return True
        if cls.secure:
            for word in cls.sensibles:
                record.msg = record.msg.replace(word, "%%%%%%%%%%")
                if (hasattr(record,'args')):
                    list_args = list(record.args)
                    for i in range(len(list_args)):
                        if not isinstance(list_args[i], str):
                            continue
                        list_args[i] = list_args[i].replace(word, "%%%%%%%%%%")
                    record.args = tuple(list_args)
        if record.levelno == logging.DEBUG and cls.quietDebug:
            for msg in cls.message_to_drop:
                if record.msg == msg:
                    return False
            for pattern in cls.pattern_to_drop:
                if re.search(pattern,record.msg):
                    return False
        return True

signalrcore.helpers.Helpers.get_logger().addFilter(logFilter())
logging.getLogger('websocket').addFilter(logFilter())
logging.getLogger('urllib3.connectionpool').addFilter(logFilter())
