<?php

namespace App\Support;

/**
 * Имена навыков SCUM для RCON-команды SetSkillLevel (максимум 4).
 * Единственный источник правды: PlayersController, WargmService и ScumDbController.
 */
class ScumSkills
{
    public const RCON_NAMES = [
        'Archery', 'Aviation', 'Awareness', 'Brawling', 'Camouflage', 'Cooking',
        'Demolition', 'Driving', 'Endurance', 'Engineering', 'Farming', 'Handgun',
        'Medical', 'Motorcycling', 'Resistance', 'Rifles', 'Running', 'Sniping',
        'Stealth', 'Survival', 'Tactics', 'Thievery', '"Melee Weapons"',
    ];
}
