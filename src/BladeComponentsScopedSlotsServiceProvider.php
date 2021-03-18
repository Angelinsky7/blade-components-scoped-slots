<?php

namespace KonradKalemba\BladeComponentsScopedSlots;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladeComponentsScopedSlotsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the scoped scope service provider
     *
     * @return void
     */
    public function boot()
    {
        Blade::directive('scopedslot', function ($expression) {
            // Split the expression by `top-level` commas (not in parentheses)
            $directiveArguments = preg_split("/,(?![^\(\(]*[\)\)])/", $expression);
            $directiveArguments = array_map('trim', $directiveArguments);

            // Ensure that the directive's arguments array has 3 elements - otherwise fill with `null`
            $directiveArguments = array_pad($directiveArguments, 3, null);

            // Extract values from the directive's arguments array
            [$name, $functionArguments, $functionUses] = $directiveArguments;

            $functionUses = array_filter(explode(',', trim($functionUses, '()')), 'strlen');

            // Add `$__env` and `$component` to allow usage of other Blade directives inside the scoped slot
            $functionUses = array_merge($functionUses, ['$component', '$__env']);
            $functionUses = implode(',', $functionUses);

            return "<?php \$__env->slot({$name}, function({$functionArguments}) use ({$functionUses}) { ?>";
        });

        Blade::directive('endscopedslot', function () {
            return "<?php }); ?>";
        });

        Blade::precompiler(function ($value) {
            $value = preg_replace_callback('/<\s*x[\-\:]scoped-slot\s+(:?)name=(?<name>(\"[^\"]+\"|\\\'[^\\\']+\\\'|[^\s>]+))\s*(context=(?<context>(\"[^\"]+\"|\\\'[^\\\']+\\\'|[^\s>]+))\s*>/', function ($matches) {
                $name = $this->stripQuotes($matches['name']);
                $context = $this->stripQuotes($matches['context']);

                if ($matches[1] !== ':') {
                    $name = "'{$name}'";
                }

                return " @scopedslot({$name}, {$context}) ";
            }, $value);

            return preg_replace('/<\/\s*x[\-\:]scoped-slot[^>]*>/', ' @endscopedslot', $value);
        });

    }

    /**
     * Strip any quotes from the given string. (Copied from Illuminate\View\Compilers\ComponentTagCompiler)
     *
     * @param  string  $value
     * @return string
     */
    public function stripQuotes(string $value)
    {
        return Str::startsWith($value, ['"', '\''])
        ? substr($value, 1, -1)
        : $value;
    }

}
