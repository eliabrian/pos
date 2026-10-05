<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:suspend-expired-tenants')->daily();
