<?php

namespace wayborne\twiggrab\models;

use craft\base\Model;

class Settings extends Model
{
    /**
     * The key to toggle grab mode. Ignored when focus is in an input/textarea.
     */
    public string $shortcutKey = 'g';

    protected function defineRules(): array
    {
        return [
            ['shortcutKey', 'required'],
            ['shortcutKey', 'string', 'length' => 1],
        ];
    }
}
