<?php

namespace PleskExtLaravel\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\LogicException;

class DebugCommand extends Command
{
    protected $signature = 'plesk-ext-laravel:debug';

    protected $description = 'Debug command registration issues';

    public function handle()
    {
        $this->info('=== Debugging Command Registration ===');
        
        try {
            // Get the application instance
            $app = $this->getApplication();
            
            // Check registered commands
            $this->line("\nRegistered commands:");
            $commands = $app->all();
            
            $pleskCommands = array_filter($commands, function($cmd, $name) {
                return str_starts_with($name, 'plesk-ext-laravel:');
            }, ARRAY_FILTER_USE_BOTH);
            
            foreach ($pleskCommands as $name => $command) {
                $this->line("  - {$name}: " . get_class($command));
                
                // Check for quiet option
                $definition = $command->getDefinition();
                if ($definition->hasOption('quiet')) {
                    $this->warn("    ⚠️  Has 'quiet' option!");
                    $quiet = $definition->getOption('quiet');
                    $this->line("       Description: " . $quiet->getDescription());
                }
            }
            
            // Check global options
            $this->line("\nGlobal application options:");
            $globalDef = $app->getDefinition();
            foreach ($globalDef->getOptions() as $option) {
                $this->line("  - " . $option->getName() . " (" . $option->getShortcut() . ")");
            }
            
        } catch (LogicException $e) {
            $this->error("\nLogicException caught: " . $e->getMessage());
            $this->line("This typically means there's a conflict in option registration.");
            $this->line("\nPossible causes:");
            $this->line("1. A command is trying to register an option that already exists globally");
            $this->line("2. Two commands are trying to register the same option");
            $this->line("3. A service provider is modifying command definitions incorrectly");
            
            // Try to get more details
            $this->line("\nDebug information:");
            $this->line("Exception class: " . get_class($e));
            $this->line("File: " . $e->getFile());
            $this->line("Line: " . $e->getLine());
            
            // Show partial stack trace
            $this->line("\nStack trace (top 5 frames):");
            $trace = $e->getTrace();
            for ($i = 0; $i < min(5, count($trace)); $i++) {
                $frame = $trace[$i];
                $file = $frame['file'] ?? 'unknown';
                $line = $frame['line'] ?? 0;
                $function = $frame['function'] ?? 'unknown';
                $class = $frame['class'] ?? '';
                
                $this->line(sprintf(
                    "  #%d %s:%d %s%s()",
                    $i,
                    basename($file),
                    $line,
                    $class ? $class . '::' : '',
                    $function
                ));
            }
        } catch (\Exception $e) {
            $this->error("\nUnexpected error: " . $e->getMessage());
            $this->line("Stack trace:\n" . $e->getTraceAsString());
        }
    }
}