<?php

namespace App\Support;

class LogParser
{
    /**
     * Универсальный парсер строки лога в структурированный массив
     */
    public static function parse(string $type, string $rawMessage): array
    {
        $raw = trim($rawMessage);
        $scumTime = null;
        $cleanMessage = $raw;

        // Отделяем timestamp SCUM, если он есть в начале строки
        if (preg_match('/^(\d{4}\.\d{2}\.\d{2}-\d{2}\.\d{2}\.\d{2}):\s*(.*)$/s', $raw, $tm)) {
            $scumTime = $tm[1];
            $cleanMessage = $tm[2];
        }

        $base = [
            'type' => $type,
            'raw' => $raw,
            'clean' => $cleanMessage,
            'scumTime' => $scumTime,
            'parsed' => false,
            'action' => null,
            'badgeText' => null,
            'badgeClass' => null,
            'player' => null,
            'steamId' => null,
            'target' => null,
            'targetSteamId' => null,
            'coords' => null,
            'details' => null,
            'extra' => [],
        ];

        return match ($type) {
            'admin' => self::parseAdmin($base, $cleanMessage),
            'kill' => self::parseKill($base, $cleanMessage),
            'login' => self::parseLogin($base, $cleanMessage),
            'quests' => self::parseQuest($base, $cleanMessage),
            'economy' => self::parseEconomy($base, $cleanMessage),
            'chest' => self::parseChest($base, $cleanMessage),
            'vehicle' => self::parseVehicle($base, $cleanMessage),
            'gameplay' => self::parseGameplay($base, $cleanMessage),
            'online' => self::parseOnline($base, $cleanMessage),
            default => self::parseGeneric($base, $cleanMessage),
        };
    }

    public static function parseAdmin(array $base, string $msg): array
    {
        $base['parsed'] = true;
        $base['action'] = 'command';
        $base['badgeText'] = '⌨️ Команда';
        $base['badgeClass'] = 'bg-[#21262d] text-[#c9d1d9] border-[#30363d]';
        $command = $msg;

        if (preg_match("/'(\d+):([^(]+)(?:\(\d+\))?'\s+Command:\s*'(.+)'/i", $msg, $m)) {
            $base['steamId'] = trim($m[1]);
            $base['player'] = trim($m[2]);
            $command = trim($m[3]);
        } elseif (preg_match("/'(\d+):([^(]+)(?:\(\d+\))?'\s+(.+)/i", $msg, $m)) {
            $base['steamId'] = trim($m[1]);
            $base['player'] = trim($m[2]);
            $command = trim($m[3]);
        } elseif (preg_match('/Kill:\s*(.+?)\s*\((\d+)\)\s*killed\s*(.+?)\s*\((\d+)\)/i', $msg, $km)) {
            $base['player'] = trim($km[1]);
            $base['steamId'] = trim($km[2]);
            $base['target'] = trim($km[3]);
            $base['targetSteamId'] = trim($km[4]);
            $base['action'] = 'kill';
            $base['badgeText'] = '💀 Убийство';
            $base['badgeClass'] = 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
            $base['details'] = 'Убийство игрока '.$base['target'];

            return $base;
        }

        if (preg_match('/^(spawnitem|spawngenerateditem|spawninventoryitem)\s*(.*)/i', $command, $cm)) {
            $base['action'] = 'spawn_item';
            $base['badgeText'] = '📦 Спавн предмета';
            $base['badgeClass'] = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
        } elseif (preg_match('/^(spawnvehicle|spawnzombie|spawnanimal)\s*(.*)/i', $command, $cm)) {
            $base['action'] = 'spawn_entity';
            $base['badgeText'] = '🚗 Спавн сущности';
            $base['badgeClass'] = 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30';
        } elseif (preg_match('/^(teleport|teleportto|tpto)\s*(.*)/i', $command, $cm)) {
            $base['action'] = 'teleport';
            $base['badgeText'] = '⚡ Телепорт';
            $base['badgeClass'] = 'bg-[#a371f7]/15 text-[#a371f7] border-[#a371f7]/30';
        } elseif (preg_match('/^(setimmortality|god|immortal)/i', $command)) {
            $base['action'] = 'godmode';
            $base['badgeText'] = '🛡️ Бессмертие';
            $base['badgeClass'] = 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30';
        } elseif (preg_match('/^(setfamepoints|setmoney|setgold)/i', $command)) {
            $base['action'] = 'currency';
            $base['badgeText'] = '💰 Валюта/Слава';
            $base['badgeClass'] = 'bg-[#f0883e]/15 text-[#f0883e] border-[#f0883e]/30';
        } elseif (preg_match('/^(ban|kick|mute)/i', $command)) {
            $base['action'] = 'punish';
            $base['badgeText'] = '⛔ Наказание';
            $base['badgeClass'] = 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
        }

        $base['details'] = $command;

        return $base;
    }

    public static function parseKill(array $base, string $msg): array
    {
        $base['parsed'] = true;
        $base['badgeText'] = '💀 Убийство';
        $base['badgeClass'] = 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';

        // Формат: Died: Victim (SteamID), Killer: Killer (SteamID) Weapon: ... S:[Distance: 10.81 m]
        if (preg_match('/Died:\s*(.+?)\s*\((\d+)\),\s*Killer:\s*(.+?)\s*\(([^)]+)\)\s*Weapon:\s*([^S\n]+)(?:S:\[(.*)\])?/i', $msg, $m)) {
            $base['target'] = trim($m[1]);
            $base['targetSteamId'] = trim($m[2]);
            $base['player'] = trim($m[3]);
            $base['steamId'] = trim($m[4]);
            $base['extra']['weapon'] = trim($m[5]);

            if (! empty($m[6]) && preg_match('/Distance:\s*([\d.]+)\s*m/i', $m[6], $dm)) {
                $base['extra']['distance'] = $dm[1].' м';
            }
            if (! empty($m[6]) && preg_match('/VictimLoc:\s*([-\d.]+),\s*([-\d.]+),\s*([-\d.]+)/i', $m[6], $lm)) {
                $base['coords'] = ['x' => (float) $lm[1], 'y' => (float) $lm[2], 'z' => (float) $lm[3]];
            }
            $base['details'] = $base['player'].' ликвидировал '.$base['target'];

            return $base;
        }

        // JSON формат лога убийств
        if (str_starts_with($msg, '{') && ($json = json_decode($msg, true))) {
            $killer = $json['Killer'] ?? [];
            $victim = $json['Victim'] ?? [];
            $base['player'] = $killer['ProfileName'] ?? 'Неизвестный';
            $base['steamId'] = ($killer['UserId'] ?? '') !== 'NPC' ? ($killer['UserId'] ?? null) : 'NPC';
            $base['target'] = $victim['ProfileName'] ?? 'Жертва';
            $base['targetSteamId'] = $victim['UserId'] ?? null;
            $base['extra']['weapon'] = $json['Weapon'] ?? 'Оружие';
            if (! empty($victim['ServerLocation'])) {
                $base['coords'] = [
                    'x' => (float) ($victim['ServerLocation']['X'] ?? 0),
                    'y' => (float) ($victim['ServerLocation']['Y'] ?? 0),
                    'z' => (float) ($victim['ServerLocation']['Z'] ?? 0),
                ];
            }
            $base['details'] = $base['player'].' ликвидировал '.$base['target'];

            return $base;
        }

        $base['details'] = $msg;

        return $base;
    }

    public static function parseLogin(array $base, string $msg): array
    {
        $base['parsed'] = true;

        if (preg_match("/'([^\s]+)\s+(\d+):([^(]+)\(\d+\)'\s+(logged in|logged out)\s+at:\s*X=([-\d.]+)\s+Y=([-\d.]+)\s+Z=([-\d.]+)/i", $msg, $m)) {
            $base['extra']['ip'] = $m[1];
            $base['steamId'] = $m[2];
            $base['player'] = trim($m[3]);
            $isLogin = strtolower($m[4]) === 'logged in';
            $base['action'] = $isLogin ? 'login' : 'logout';
            $base['badgeText'] = $isLogin ? '🟢 Вход' : '🔴 Выход';
            $base['badgeClass'] = $isLogin ? 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30' : 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
            $base['coords'] = ['x' => (float) $m[5], 'y' => (float) $m[6], 'z' => (float) $m[7]];
            $base['details'] = $isLogin ? 'Подключение к серверу' : 'Отключение от сервера';

            return $base;
        }

        if (preg_match('/^(.+?)\s+(connected|disconnected)$/i', $msg, $m)) {
            $base['player'] = trim($m[1]);
            $isLogin = strtolower($m[2]) === 'connected';
            $base['action'] = $isLogin ? 'login' : 'logout';
            $base['badgeText'] = $isLogin ? '🟢 Вход' : '🔴 Выход';
            $base['badgeClass'] = $isLogin ? 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30' : 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
            $base['details'] = $isLogin ? 'Подключение к серверу' : 'Отключение от сервера';

            return $base;
        }

        $base['details'] = $msg;

        return $base;
    }

    public static function parseQuest(array $base, string $msg): array
    {
        $base['parsed'] = true;
        // [LogQuestStatus] Victoria_Rayevskaya (20, 76561198390716853) completed quest T1_MC_Interact_DisableTVs
        if (preg_match('/\[LogQuestStatus\]\s*(.+?)\s*\(\d+,\s*(\d+)\)\s*(completed|abandoned|started)\s+quest\s+(.+)/i', $msg, $m)) {
            $base['player'] = trim($m[1]);
            $base['steamId'] = trim($m[2]);
            $st = strtolower($m[3]);
            $base['action'] = $st;
            $rawQuest = trim($m[4]);
            $base['extra']['questRaw'] = $rawQuest;
            $base['extra']['questName'] = str_replace(['_', '.json'], [' ', ''], $rawQuest);

            if ($st === 'completed') {
                $base['badgeText'] = '✅ Квест выполнен';
                $base['badgeClass'] = 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30';
            } elseif ($st === 'abandoned') {
                $base['badgeText'] = '❌ Квест отменён';
                $base['badgeClass'] = 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
            } else {
                $base['badgeText'] = '⏳ Квест начат';
                $base['badgeClass'] = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
            }
            $base['details'] = $base['extra']['questName'];

            return $base;
        }

        $base['details'] = $msg;

        return $base;
    }

    public static function parseEconomy(array $base, string $msg): array
    {
        $base['parsed'] = true;
        // [Bank] Player(ID:76561198052889185)... deposited 183
        if (preg_match('/\[Bank\]\s*(.+?)\(ID:(\d+)\).*?\s+(deposited|withdrew)\s+(\d+)/i', $msg, $m)) {
            $base['player'] = trim($m[1]);
            $base['steamId'] = trim($m[2]);
            $act = strtolower($m[3]) === 'deposited' ? 'Вклад' : 'Снятие';
            $base['badgeText'] = '🏦 Банк: '.$act;
            $base['badgeClass'] = 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30';
            $base['extra']['amount'] = '$'.number_format((int) $m[4], 0, '.', ' ');
            $base['details'] = $act.' средств на сумму '.$base['extra']['amount'];

            return $base;
        }

        // [Trade] Tradeable (Donkey_Head) sold by Player(ID) for 192 to trader B_4_Hunter
        if (preg_match('/\[Trade\]\s*Tradeable\s*\((.+?)\)\s*sold by\s*(.+?)\((\d+)\)\s*for\s*(\d+).*?to trader\s*(\S+)/i', $msg, $m)) {
            $base['extra']['item'] = trim(explode('(', $m[1])[0]);
            $base['player'] = trim($m[2]);
            $base['steamId'] = trim($m[3]);
            $base['extra']['amount'] = '$'.number_format((int) $m[4], 0, '.', ' ');
            $base['extra']['trader'] = trim($m[5]);
            $base['badgeText'] = '🛒 Продажа торговцу';
            $base['badgeClass'] = 'bg-[#f0883e]/15 text-[#f0883e] border-[#f0883e]/30';
            $base['details'] = 'Продажа: '.$base['extra']['item'].' за '.$base['extra']['amount'].' ('.$base['extra']['trader'].')';

            return $base;
        }

        if (preg_match('/\[Trade\]/i', $msg)) {
            $base['badgeText'] = '💰 Торговля';
            $base['badgeClass'] = 'bg-[#f0883e]/15 text-[#f0883e] border-[#f0883e]/30';
        }

        $base['details'] = $msg;

        return $base;
    }

    public static function parseChest(array $base, string $msg): array
    {
        $base['parsed'] = true;
        // Chest (entity id: 1010026) ownership changed. Old owner: 76561198405033520 (3, Name) -> New owner: 76561198280271012 (18, Name). Location: X=... Y=...
        if (preg_match('/Chest\s*\(entity id:\s*(\d+)\)\s*ownership changed\.\s*Old owner:\s*(\d+)\s*\([^,]+,\s*([^)]+)\)\s*->\s*New owner:\s*(\d+)\s*\([^,]+,\s*([^)]+)\)/i', $msg, $m)) {
            $base['extra']['entityId'] = $m[1];
            $base['extra']['oldSteamId'] = $m[2];
            $base['extra']['oldOwner'] = trim($m[3]);
            $base['steamId'] = $m[4];
            $base['player'] = trim($m[5]);
            $base['badgeText'] = '📦 Смена владельца';
            $base['badgeClass'] = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
            if (preg_match('/Location:\s*X=([-\d.]+)\s+Y=([-\d.]+)\s+Z=([-\d.]+)/i', $msg, $lm)) {
                $base['coords'] = ['x' => (float) $lm[1], 'y' => (float) $lm[2], 'z' => (float) $lm[3]];
            }
            $base['details'] = 'Сундук #'.$base['extra']['entityId'].': '.$base['extra']['oldOwner'].' ➔ '.$base['player'];

            return $base;
        }

        if (preg_match('/Location:\s*X=([-\d.]+)\s+Y=([-\d.]+)\s+Z=([-\d.]+)/i', $msg, $lm)) {
            $base['coords'] = ['x' => (float) $lm[1], 'y' => (float) $lm[2], 'z' => (float) $lm[3]];
        }

        $base['badgeText'] = '📦 Сундук';
        $base['badgeClass'] = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
        $base['details'] = $msg;

        return $base;
    }

    public static function parseVehicle(array $base, string $msg): array
    {
        $base['parsed'] = true;
        $base['badgeText'] = '🚗 Транспорт';
        $base['badgeClass'] = 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30';

        // Coords: Location: X=... Y=... Z=...
        if (preg_match('/Location:\s*X=([-\d.]+)\s+Y=([-\d.]+)\s+Z=([-\d.]+)/i', $msg, $lm)) {
            $base['coords'] = ['x' => (float) $lm[1], 'y' => (float) $lm[2], 'z' => (float) $lm[3]];
        }

        // Patterns:
        // [Destroyed] Dirtbike_ES. VehicleId: 3281935. Owner: N/A. Location: X=...
        // [VehicleInactiveTimerReached] CityBike_ES. VehicleId: 2550246. Owner: N/A. Location: X=...
        // [Disappeared] CityBike_ES. VehicleId: 2550246. Owner: N/A. Location: X=...
        // [Converted to item] Dinghy_ES. VehicleId: 3260052. Owner: N/A. Location: X=...
        // [Spawner removed - converted to item] Dinghy_ES. VehicleId: 3260052. Owner: N/A. Location: X=...
        if (preg_match('/\[([^\]]+)\]\s*([^.]+)\.\s*VehicleId:\s*(\d+)\.\s*Owner:\s*([^.]+)\./i', $msg, $m)) {
            $rawAction = trim($m[1]);
            $rawModel = trim($m[2]);
            $vehicleId = trim($m[3]);
            $rawOwner = trim($m[4]);

            $cleanModel = str_replace(['_ES', '_C', 'BP_'], '', $rawModel);
            $cleanModel = preg_replace('/(?<!^)([A-Z])/', ' $1', $cleanModel); // CamelCase to words

            $base['extra']['model'] = $cleanModel;
            $base['extra']['vehicleId'] = $vehicleId;
            $base['extra']['rawAction'] = $rawAction;

            if ($rawOwner !== 'N/A' && ! empty($rawOwner)) {
                if (preg_match('/(\d+)\s*\(([^)]+)\)/', $rawOwner, $om)) {
                    $base['steamId'] = $om[1];
                    $base['player'] = trim($om[2]);
                } elseif (preg_match('/(\d{17})/', $rawOwner, $om)) {
                    $base['steamId'] = $om[1];
                    $base['player'] = 'Владелец';
                } else {
                    $base['player'] = $rawOwner;
                }
            } else {
                $base['player'] = $cleanModel.' #'.$vehicleId;
            }

            if (stripos($rawAction, 'Destroyed') !== false) {
                $base['action'] = 'destroyed';
                $base['badgeText'] = '💥 Уничтожен';
                $base['badgeClass'] = 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
                $base['details'] = "Транспорт {$cleanModel} (#{$vehicleId}) был уничтожен";
            } elseif (stripos($rawAction, 'Disappeared') !== false) {
                $base['action'] = 'disappeared';
                $base['badgeText'] = '💨 Исчез';
                $base['badgeClass'] = 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30';
                $base['details'] = "Транспорт {$cleanModel} (#{$vehicleId}) исчез с сервера";
            } elseif (stripos($rawAction, 'InactiveTimer') !== false) {
                $base['action'] = 'inactive_timer';
                $base['badgeText'] = '⏱️ Истёк таймер';
                $base['badgeClass'] = 'bg-[#30363d]/40 text-[#8b949e] border-[#30363d]';
                $base['details'] = "Сработал таймер неактивности для {$cleanModel} (#{$vehicleId})";
            } elseif (stripos($rawAction, 'converted to item') !== false) {
                $base['action'] = 'itemized';
                $base['badgeText'] = '📦 В предмет';
                $base['badgeClass'] = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
                $base['details'] = "Транспорт {$cleanModel} (#{$vehicleId}) упакован в предмет";
            } else {
                $base['action'] = 'vehicle';
                $base['badgeText'] = '🚗 '.$rawAction;
                $base['details'] = "Транспорт {$cleanModel} (#{$vehicleId}): {$rawAction}";
            }

            if ($rawOwner !== 'N/A' && ! empty($rawOwner)) {
                $base['details'] .= " (Владелец: {$rawOwner})";
            }

            return $base;
        }

        $base['details'] = $msg;

        return $base;
    }

    public static function parseGameplay(array $base, string $msg): array
    {
        $base['parsed'] = true;
        // [LogBunkerLock] C4 Bunker is Locked. Locked 00h 00m 00s ago, next Activation in 19h 00m 52s. X=...
        if (preg_match('/\[LogBunkerLock\]\s*(.+?)\s+is\s+(Locked|Active)\.(.*?)(?:X=([-\d.]+)\s+Y=([-\d.]+)\s+Z=([-\d.]+))?$/i', $msg, $m)) {
            $bunker = trim($m[1]);
            $isActive = strtolower($m[2]) === 'active';
            $base['badgeText'] = $isActive ? '⚡ Бункер активен' : '🔒 Бункер закрыт';
            $base['badgeClass'] = $isActive ? 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30' : 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
            $base['extra']['bunker'] = $bunker;
            if (! empty($m[4])) {
                $base['coords'] = ['x' => (float) $m[4], 'y' => (float) $m[5], 'z' => (float) $m[6]];
            }
            $base['details'] = $bunker.' — '.($isActive ? 'Открыт / активен' : 'Заблокирован').' '.trim($m[3]);

            return $base;
        }

        $base['badgeText'] = '🎮 Геймплей';
        $base['badgeClass'] = 'bg-[#8957e5]/15 text-[#a371f7] border-[#8957e5]/30';
        $base['details'] = $msg;

        return $base;
    }

    public static function parseOnline(array $base, string $msg): array
    {
        $base['parsed'] = true;
        if (preg_match('/(?:Онлайн|P):\s*(\d+)/i', $msg, $m)) {
            $count = (int) $m[1];
            $base['badgeText'] = '👁️ Онлайн: '.$count;
            $base['badgeClass'] = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
            $base['extra']['onlineCount'] = $count;
            $base['details'] = 'Текущий онлайн сервера: '.$count.' игроков';

            return $base;
        }
        $base['badgeText'] = '👁️ Онлайн';
        $base['badgeClass'] = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
        $base['details'] = $msg;

        return $base;
    }

    public static function parseGeneric(array $base, string $msg): array
    {
        $base['details'] = $msg;
        $base['badgeText'] = '📋 Лог';
        $base['badgeClass'] = 'bg-[#21262d] text-[#8b949e] border-[#30363d]';

        return $base;
    }
}
