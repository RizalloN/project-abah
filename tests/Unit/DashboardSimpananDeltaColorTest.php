<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use ReflectionMethod;
use Tests\TestCase;

class DashboardSimpananDeltaColorTest extends TestCase
{
    public function test_format_area6_card_delta_colors_sml_and_npl_correctly(): void
    {
        $controller = new DashboardSimpananController();
        $method = new ReflectionMethod(DashboardSimpananController::class, 'formatArea6CardDelta');
        $method->setAccessible(true);

        // SML: negative/turun (*) is green, positive/naik is red
        $smlDown = $method->invoke($controller, -30_161_000_000, 'sml');
        $this->assertSame('green', $smlDown['color']);
        $this->assertSame('(30.161)', $smlDown['value']);
        $this->assertSame('down', $smlDown['type']);

        $smlUp = $method->invoke($controller, 522_343_000_000, 'sml');
        $this->assertSame('red', $smlUp['color']);
        $this->assertSame('+522.343', $smlUp['value']);
        $this->assertSame('up', $smlUp['type']);

        // NPL: negative/turun (*) is green, positive/naik is red
        $nplDown = $method->invoke($controller, -14_000_000, 'npl');
        $this->assertSame('green', $nplDown['color']);
        $this->assertSame('(14)', $nplDown['value']);
        $this->assertSame('down', $nplDown['type']);

        $nplUp = $method->invoke($controller, 27_407_000_000, 'npl');
        $this->assertSame('red', $nplUp['color']);
        $this->assertSame('+27.407', $nplUp['value']);
        $this->assertSame('up', $nplUp['type']);

        // OS: positive/naik is green, negative/turun is red
        $osUp = $method->invoke($controller, 45_971_000_000, 'os');
        $this->assertSame('green', $osUp['color']);
        $this->assertSame('+45.971', $osUp['value']);
        $this->assertSame('up', $osUp['type']);

        $osDown = $method->invoke($controller, -10_000_000_000, 'os');
        $this->assertSame('red', $osDown['color']);
        $this->assertSame('(10.000)', $osDown['value']);
        $this->assertSame('down', $osDown['type']);
    }

    public function test_landing_blade_renders_sml_and_npl_delta_colors_correctly(): void
    {
        $view = $this->blade('
            @foreach($cards as $card)
              @php
                $key = data_get($card, "key");
                $deltas = data_get($card, "deltas", []);
              @endphp
              <div class="card-{{ $key }}">
                @foreach(["dtd" => "DtD", "mtd" => "MtD", "mom" => "MtM", "ytd" => "YtD"] as $dKey => $dLabel)
                  @php
                    $delta = data_get($deltas, $dKey, []);
                    $deltaVal = trim((string) data_get($delta, "value", "-"));
                    $deltaType = (string) data_get($delta, "type", "up");
                    $deltaRaw = data_get($delta, "raw");

                    $cleanNum = preg_replace("/[^\d]/", "", $deltaVal);
                    $isZero = ($cleanNum === "0" || $deltaVal === "-" || $deltaVal === "");

                    $isNegative = !$isZero && (
                        str_starts_with($deltaVal, "(")
                        || $deltaType === "down"
                        || (is_numeric($deltaRaw) && (float) $deltaRaw < 0)
                    );

                    $isPositive = !$isZero && (
                        str_starts_with($deltaVal, "+")
                        || ($deltaType === "up" && !str_starts_with($deltaVal, "("))
                        || (is_numeric($deltaRaw) && (float) $deltaRaw > 0)
                    );

                    if (in_array($key, ["sml", "npl"], true)) {
                      if ($isNegative) {
                        $deltaColor = "green";
                        $deltaArrow = "down";
                      } elseif ($isPositive) {
                        $deltaColor = "red";
                        $deltaArrow = "up";
                      } else {
                        $deltaColor = "green";
                        $deltaArrow = $deltaType === "down" ? "down" : ($deltaType === "up" ? "up" : "minus");
                      }
                    } else {
                      if ($isNegative) {
                        $deltaColor = "red";
                        $deltaArrow = "down";
                      } elseif ($isPositive) {
                        $deltaColor = "green";
                        $deltaArrow = "up";
                      } else {
                        $deltaColor = "green";
                        $deltaArrow = $deltaType === "down" ? "down" : ($deltaType === "up" ? "up" : "minus");
                      }
                    }
                  @endphp
                  <span class="{{ $dKey }}-val text-{{ $deltaColor }}-flat">{{ $deltaVal }}</span>
                @endforeach
              </div>
            @endforeach
        ', [
            'cards' => [
                [
                    'key' => 'sml',
                    'deltas' => [
                        'dtd' => ['value' => '(30.161)', 'type' => 'down', 'color' => 'red'], // Even if backend sent 'red' by accident!
                        'mtd' => ['value' => '+522.343', 'type' => 'up', 'color' => 'green'], // Even if backend sent 'green'!
                    ],
                ],
                [
                    'key' => 'npl',
                    'deltas' => [
                        'dtd' => ['value' => '(14)', 'type' => 'down'],
                        'mtd' => ['value' => '+27.407', 'type' => 'up'],
                    ],
                ],
                [
                    'key' => 'os',
                    'deltas' => [
                        'dtd' => ['value' => '+45.971', 'type' => 'up'],
                        'mtd' => ['value' => '(10.000)', 'type' => 'down'],
                    ],
                ],
            ],
        ]);

        // SML assertions
        $view->assertSee('text-green-flat">(30.161)', false);
        $view->assertSee('text-red-flat">+522.343', false);

        // NPL assertions
        $view->assertSee('text-green-flat">(14)', false);
        $view->assertSee('text-red-flat">+27.407', false);

        // OS assertions
        $view->assertSee('text-green-flat">+45.971', false);
        $view->assertSee('text-red-flat">(10.000)', false);
    }
}

