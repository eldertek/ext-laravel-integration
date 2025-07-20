<?php

namespace PleskExtLaravel\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\LogicException;

class DebugCommand extends Command
{
    protected $signature = 'plesk-ext-laravel:debug {--trace-quiet : Trace quiet option registrations}';

    protected $description = 'Debug command registration issues';

    public function handle()
    {
        $this->info('=== Debugging Command Registration ===');
        
        // Check if we should trace quiet option
        if ($this->option('trace-quiet')) {
            $this->traceQuietOption();
            return;
        }
        
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
    
    /**
     * Trace where the quiet option is being registered
     */
    protected function traceQuietOption()
    {
        $this->info('=== Tracing Quiet Option Registrations ===');
        $this->line('');
        
        // Test 1: Check if Laravel adds quiet by default
        $this->line('1. Checking Laravel default command options:');
        
        // Create a minimal test command
        $testCommand = new class extends Command {
            protected $signature = 'test:minimal';
            protected $description = 'Minimal test command';
            
            public function handle() {
                // Empty
            }
        };
        
        $definition = $testCommand->getDefinition();
        $hasQuiet = $definition->hasOption('quiet');
        $this->line('   Minimal command has quiet option: ' . ($hasQuiet ? 'YES' : 'NO'));
        
        if ($hasQuiet) {
            $quiet = $definition->getOption('quiet');
            $this->line('   - Shortcut: ' . $quiet->getShortcut());
            $this->line('   - Description: ' . $quiet->getDescription());
        }
        
        // Test 2: Check when quiet is added
        $this->line('');
        $this->line('2. Checking when quiet option is added:');
        
        // Hook into the application to trace
        $app = $this->getApplication();
        $this->line('   Application class: ' . get_class($app));
        
        // Check if app has global quiet option
        $appDef = $app->getDefinition();
        $appHasQuiet = $appDef->hasOption('quiet');
        $this->line('   Application has global quiet: ' . ($appHasQuiet ? 'YES' : 'NO'));
        
        // Test 3: Try to identify the conflict source
        $this->line('');
        $this->line('3. Analyzing potential conflicts:');
        
        // Get all registered commands
        $commands = $app->all();
        $conflictingCommands = [];
        
        foreach ($commands as $name => $command) {
            try {
                $def = $command->getDefinition();
                $options = $def->getOptions();
                
                foreach ($options as $option) {
                    if ($option->getName() === 'quiet') {
                        // Check if this is different from the global quiet
                        if ($appHasQuiet) {
                            $globalQuiet = $appDef->getOption('quiet');
                            if ($option !== $globalQuiet) {
                                $conflictingCommands[] = [
                                    'name' => $name,
                                    'class' => get_class($command),
                                    'description' => $option->getDescription(),
                                    'shortcut' => $option->getShortcut(),
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->warn("   Error checking command '{$name}': " . $e->getMessage());
            }
        }
        
        if (empty($conflictingCommands)) {
            $this->info('   No conflicting quiet options found.');
        } else {
            $this->warn('   Found commands with different quiet options:');
            foreach ($conflictingCommands as $cmd) {
                $this->line("   - {$cmd['name']} ({$cmd['class']})");
                $this->line("     Description: {$cmd['description']}");
                $this->line("     Shortcut: {$cmd['shortcut']}");
            }
        }
        
        // Test 4: Check inheritance chain
        $this->line('');
        $this->line('4. Checking command inheritance:');
        
        $this->line('   DebugCommand inheritance chain:');
        $class = new \ReflectionClass($this);
        $indent = '   ';
        while ($class) {
            $this->line($indent . '- ' . $class->getName());
            if ($class->getName() === Command::class || $class->getName() === 'Symfony\Component\Console\Command\Command') {
                break;
            }
            $indent .= '  ';
            $class = $class->getParentClass();
        }
        
        $this->line('');
        $this->info('=== Trace Complete ===');
        $this->line('');
        $this->line('To fix the "quiet option already exists" error:');
        $this->line('1. Ensure commands don\'t manually add a quiet option');
        $this->line('2. Check if any service providers are modifying command definitions');
        $this->line('3. Verify no command is trying to override the global quiet option');
    }
}