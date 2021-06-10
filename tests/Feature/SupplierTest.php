<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    /**
     * In the task we need to calculate amount of hours suppliers are working during last week for marketing.
     * You can use any way you like to do it, but remember, in real life we are about to have 400+ real
     * suppliers.
     *
     * @return void
     */
    public function testCalculateAmountOfHoursDuringTheWeekSuppliersAreWorking()
    {
        $response = $this->get('/api/suppliers');
        $suppliers = collect(\json_decode($response->getContent(), true)['data']['suppliers']);

        $weekDays = collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);

        Collection::macro('splitByDash', function () {
            return $this->map(function ($value) {
                return explode('-', $value);
            });
        });

        Collection::macro('removeExtraCharacters', function () {
            return $this->map(function ($value) {
                return preg_replace('/[^0-9.]+: /', '', $value);
            });
        });

        $workingHours = collect([]);
        $suppliers->map(function ($name) use($weekDays, $workingHours) {
            $weekDays->map(function ($day) use($name, $workingHours) {
                $dayHoursInShift = collect(explode(',', $name[$day]))->splitByDash()->removeExtraCharacters();
                $dayHoursInShift->map(function ($timeSlots) use($workingHours) {
                    $t1 = Carbon::parse("2016-07-01 {$timeSlots[0]}:00");
                    $t2 = Carbon::parse("2016-07-01 {$timeSlots[1]}:00");
                    $diff = $t1->diff($t2)->h;
                    $workingHours->push($diff);
                });
            });
        });
        $hours = $workingHours->sum();

        $response->assertStatus(200);
        $this->assertEquals(136, $hours,
            "Our suppliers are working X hours per week in total. Please, find out how much they work..");
    }

    /**
     * Save the first supplier from JSON into database.
     * Please, be sure, all asserts pass.
     *
     * After you save supplier in database, in test we apply verifications on the data.
     * On last line of the test second attempt to add the supplier fails. We do not allow to add supplier with the same name.
     */
    public function testSaveSupplierInDatabase()
    {
        Supplier::query()->truncate();
        $responseList = $this->get('/api/suppliers');
        $supplier = \json_decode($responseList->getContent(), true)['data']['suppliers'][0];

        $response = $this->post('/api/suppliers', $supplier);

        $response->assertStatus(204);
        $this->assertEquals(1, Supplier::query()->count());
        $dbSupplier = Supplier::query()->first();
        $this->assertNotFalse(curl_init($dbSupplier->url));
        $this->assertNotFalse(curl_init($dbSupplier->rules));
        $this->assertGreaterThan(4, strlen($dbSupplier->info));
        $this->assertNotNull($dbSupplier->name);
        $this->assertNotNull($dbSupplier->district);


        $response = $this->post('/api/suppliers', $supplier);
        $response->assertStatus(422);
    }
}
