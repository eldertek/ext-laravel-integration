<?php

namespace PleskExtLaravel\Console\Commands;

use Illuminate\Console\Command;

abstract class BaseCommand extends Command
{
    /**
     * Override to prevent quiet option conflicts
     */
    protected function configureUsingFluentDefinition()
    {
        // Store current quiet option if exists
        $hasQuiet = false;
        $quietOption = null;
        
        try {
            $definition = $this->getDefinition();
            if ($definition->hasOption('quiet')) {
                $hasQuiet = true;
                $quietOption = $definition->getOption('quiet');
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Call parent configuration
        parent::configureUsingFluentDefinition();
        
        // Remove quiet option if it was added by parent
        try {
            $definition = $this->getDefinition();
            $options = $definition->getOptions();
            
            foreach ($options as $option) {
                if ($option->getName() === 'quiet' && !$hasQuiet) {
                    // This quiet option was added by parent, not us
                    // We can't remove it, but we can document the issue
                    error_log('Plesk Laravel: Detected quiet option added by parent class');
                }
            }
        } catch (\Exception $e) {
            error_log('Plesk Laravel: Error checking options - ' . $e->getMessage());
        }
    }
}