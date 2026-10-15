-- Copyright (C) 2026 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- Failed login attempts, used to lock a login or an IP temporarily. Rows older than the lock
-- duration are purged at each new failure.
CREATE TABLE llx_dolisecu_login_attempt(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	login varchar(128) NOT NULL,
	ip    varchar(250) NOT NULL,
	datec datetime     NOT NULL
) ENGINE=innodb;
