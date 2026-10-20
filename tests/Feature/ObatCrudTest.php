<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\DetailPeriksa;
use App\Models\JadwalPeriksa;
use App\Models\Obat;
use App\Models\Periksa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ObatCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_empty_list_and_create_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('obat.index'))->assertOk()->assertSee('Belum ada data obat');
        $this->get(route('obat.create'))->assertOk()->assertSee('Tambah Obat')->assertSee('name="harga"', false);
    }

    public function test_admin_creates_obat_and_sees_success_notification_and_rupiah_price(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post(route('obat.store'), $this->validData() + ['id' => 999])
            ->assertRedirect(route('obat.index'))
            ->assertSessionHas('success', 'Obat berhasil ditambahkan.');
        $this->assertDatabaseCount('obat', 1);
        $this->assertDatabaseHas('obat', $this->validData());
        $this->assertDatabaseMissing('obat', ['id' => 999]);

        $this->get(route('obat.index'))->assertOk()
            ->assertSee('Paracetamol 500 mg')->assertSee('Strip')->assertSee('Rp 10.000')
            ->assertSee('Obat berhasil ditambahkan.');
    }

    public function test_edit_form_contains_saved_values_and_admin_can_update_obat(): void
    {
        $obat = Obat::factory()->create(['nama_obat' => 'Obat Lama', 'harga' => 5000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('obat.edit', $obat))->assertOk()->assertSee('Obat Lama')->assertSee('value="5000"', false);
        $this->put(route('obat.update', $obat), $this->validData())
            ->assertRedirect(route('obat.index'))
            ->assertSessionHas('success', 'Obat berhasil diperbarui.');

        $this->assertDatabaseHas('obat', ['id' => $obat->id] + $this->validData());
        $this->assertDatabaseCount('obat', 1);
    }

    public function test_admin_can_delete_unused_obat(): void
    {
        $obat = Obat::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->delete(route('obat.destroy', $obat))
            ->assertRedirect(route('obat.index'))
            ->assertSessionHas('success', 'Obat berhasil dihapus.');

        $this->assertModelMissing($obat);
    }

    public function test_used_obat_cannot_be_deleted_and_examination_history_is_preserved(): void
    {
        $obat = Obat::factory()->create();
        $jadwal = JadwalPeriksa::factory()->create();
        $daftar = DaftarPoli::create([
            'id_pasien' => User::factory()->create()->id,
            'id_jadwal' => $jadwal->id, 'keluhan' => 'Demam', 'no_antrian' => 1,
        ]);
        $periksa = Periksa::create([
            'id_daftar_poli' => $daftar->id, 'tgl_periksa' => '2026-10-20 09:00:00',
            'catatan' => 'Istirahat', 'biaya_periksa' => 50000,
        ]);
        $detail = DetailPeriksa::create(['id_periksa' => $periksa->id, 'id_obat' => $obat->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->delete(route('obat.destroy', $obat))->assertRedirect(route('obat.index'))
            ->assertSessionHas('error', 'Obat tidak dapat dihapus karena sudah digunakan dalam pemeriksaan.');

        $this->assertModelExists($obat);
        $this->assertModelExists($detail);
        $this->assertModelExists($periksa);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_without_saving(string $method, array $overrides, array $errors): void
    {
        $obat = Obat::factory()->create(['nama_obat' => 'Obat Tetap', 'harga' => 5000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $uri = $method === 'POST' ? route('obat.store') : route('obat.update', $obat);

        $this->from(route('obat.create'))->call($method, $uri, array_replace($this->validData(), $overrides))
            ->assertRedirect(route('obat.create'))->assertSessionHasErrors($errors);

        $this->assertDatabaseCount('obat', 1);
        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'nama_obat' => 'Obat Tetap', 'harga' => 5000]);
    }

    public static function invalidInputs(): array
    {
        $cases = [
            'required fields' => [
                ['nama_obat' => '', 'kemasan' => '', 'harga' => ''],
                ['nama_obat' => 'Nama obat wajib diisi.', 'kemasan' => 'Kemasan wajib diisi.', 'harga' => 'Harga wajib diisi.'],
            ],
            'name too long' => [['nama_obat' => str_repeat('a', 256)], ['nama_obat' => 'Nama obat maksimal 255 karakter.']],
            'name is not text' => [['nama_obat' => ['a']], ['nama_obat' => 'Nama obat harus berupa teks.']],
            'packaging too long' => [['kemasan' => str_repeat('a', 36)], ['kemasan' => 'Kemasan maksimal 35 karakter.']],
            'packaging is not text' => [['kemasan' => ['a']], ['kemasan' => 'Kemasan harus berupa teks.']],
            'negative price' => [['harga' => -1], ['harga' => 'Harga tidak boleh negatif.']],
            'fractional price' => [['harga' => '1000.5'], ['harga' => 'Harga harus berupa bilangan bulat dalam rupiah.']],
            'price is text' => [['harga' => 'mahal'], ['harga' => 'Harga harus berupa bilangan bulat dalam rupiah.']],
            'price exceeds storage' => [['harga' => 2147483648], ['harga' => 'Harga maksimal Rp2.147.483.647.']],
        ];
        $data = [];
        foreach (['POST', 'PUT'] as $method) {
            foreach ($cases as $name => [$overrides, $errors]) {
                $data[$method.' '.$name] = [$method, $overrides, $errors];
            }
        }

        return $data;
    }

    public function test_validation_keeps_previous_input_and_displays_field_error(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->followingRedirects()->from(route('obat.create'))
            ->post(route('obat.store'), array_replace($this->validData(), ['harga' => -1]))
            ->assertOk()->assertSee('Harga tidak boleh negatif.')
            ->assertSee('value="Paracetamol 500 mg"', false);

        $this->assertDatabaseCount('obat', 0);
    }

    public function test_free_obat_with_zero_price_is_accepted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post(route('obat.store'), array_replace($this->validData(), ['harga' => 0]))
            ->assertSessionHasNoErrors()->assertRedirect(route('obat.index'));

        $this->assertDatabaseHas('obat', ['nama_obat' => 'Paracetamol 500 mg', 'harga' => 0]);
    }

    public function test_obat_name_and_packaging_are_escaped_in_list_and_edit_form(): void
    {
        $obat = Obat::factory()->create(['nama_obat' => '<script>nama</script>', 'kemasan' => '<b>kemasan</b>']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('obat.index'))->assertOk()
            ->assertSee('&lt;script&gt;nama&lt;/script&gt;', false)->assertDontSee('<script>nama</script>', false)
            ->assertSee('&lt;b&gt;kemasan&lt;/b&gt;', false)->assertDontSee('<b>kemasan</b>', false);
        $this->get(route('obat.edit', $obat))->assertOk()
            ->assertSee('&lt;script&gt;nama&lt;/script&gt;', false)->assertDontSee('<script>nama</script>', false)
            ->assertSee('&lt;b&gt;kemasan&lt;/b&gt;', false);
    }

    #[DataProvider('protectedEndpoints')]
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $obat = Obat::factory()->create();
        $uri = str_replace('{id}', (string) $obat->id, $uri);

        $this->call($method, $uri, $this->validData())->assertRedirect(route('login'));

        $this->assertModelExists($obat);
        $this->assertDatabaseCount('obat', 1);
    }

    #[DataProvider('forbiddenEndpoints')]
    public function test_non_admin_roles_cannot_access_or_change_obat(string $role, string $method, string $uri): void
    {
        $obat = Obat::factory()->create(['nama_obat' => 'Obat Tetap']);
        $this->actingAs(User::factory()->create(['role' => $role]));
        $uri = str_replace('{id}', (string) $obat->id, $uri);

        $this->call($method, $uri, $this->validData())->assertForbidden();

        $this->assertDatabaseCount('obat', 1);
        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'nama_obat' => 'Obat Tetap']);
    }

    public static function protectedEndpoints(): array
    {
        return [
            'list' => ['GET', '/admin/obat'],
            'create form' => ['GET', '/admin/obat/create'],
            'store' => ['POST', '/admin/obat'],
            'edit form' => ['GET', '/admin/obat/{id}/edit'],
            'update' => ['PUT', '/admin/obat/{id}'],
            'delete' => ['DELETE', '/admin/obat/{id}'],
        ];
    }

    public static function forbiddenEndpoints(): array
    {
        $data = [];
        foreach (['dokter', 'pasien'] as $role) {
            foreach (self::protectedEndpoints() as $name => [$method, $uri]) {
                $data[$role.' '.$name] = [$role, $method, $uri];
            }
        }

        return $data;
    }

    private function validData(): array
    {
        return ['nama_obat' => 'Paracetamol 500 mg', 'kemasan' => 'Strip', 'harga' => 10000];
    }
}
