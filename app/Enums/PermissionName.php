<?php

namespace App\Enums;

enum PermissionName: string
{
    case ManageUsers = 'users.manage';
    case ConfigureMemory = 'memory.configure';
    case CreateScenarios = 'scenarios.create';
    case ExecuteSimulations = 'simulations.execute';
    case ResetMemory = 'memory.reset';
    case ViewHistory = 'history.view';
    case CreateProcesses = 'processes.create';
    case RequestPages = 'pages.request';
    case ExecuteSegmentation = 'segmentation.execute';
    case ViewResults = 'results.view';
    case ViewMemory = 'memory.view';
    case ViewTables = 'tables.view';
    case ViewSimulations = 'simulations.view';
}
