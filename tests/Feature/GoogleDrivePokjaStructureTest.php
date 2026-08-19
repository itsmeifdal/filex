<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoogleDrivePokjaStructureTest extends TestCase
{
    public function test_ppk_document_requirements_match_the_official_evidence_list(): void
    {
        $requirements = collect(config('accreditation.document_requirements'))
            ->filter(fn (array $requirement, string $code): bool => str_starts_with($code, 'PPK '));

        $this->assertCount(23, $requirements);
        $this->assertSame(30, $requirements->sum('count'));
        $this->assertSame(2, $requirements['PPK 1 / EP 2']['count']);
        $this->assertSame(2, $requirements['PPK 1 / EP 3']['count']);
        $this->assertSame(1, $requirements['PPK 1 / EP 4']['count']);
    }

    public function test_skp_and_pmkp_document_requirements_match_the_official_evidence_list(): void
    {
        $requirements = collect(config('accreditation.document_requirements'));
        $skp = $requirements->filter(fn (array $requirement, string $code): bool => str_starts_with($code, 'SKP '));
        $pmkp = $requirements->filter(fn (array $requirement, string $code): bool => str_starts_with($code, 'PMKP '));

        $this->assertCount(24, $skp);
        $this->assertSame(21, $skp->sum('count'));
        $this->assertSame(0, $skp['SKP 1 / EP 2']['count']);
        $this->assertSame(0, $skp['SKP 1 / EP 3']['count']);
        $this->assertSame(2, $skp['SKP 3.1 / EP 3']['count']);
        $this->assertCount(44, $pmkp);
        $this->assertSame(62, $pmkp->sum('count'));
        $this->assertSame(1, $pmkp['PMKP 4.1 / EP 1']['count']);
        $this->assertSame(6, config('accreditation.drive_structures.PMKP.11'));
        $this->assertSame(3, $pmkp['PMKP 11 / EP 4']['count']);

        $requirements->each(fn (array $requirement) => $this->assertDoesNotMatchRegularExpression(
            '/\[(?:W|O|S)\]/',
            $requirement['evidence'],
        ));
    }

    public function test_kps_document_requirements_count_only_document_and_regulation_evidence(): void
    {
        $requirements = collect(config('accreditation.document_requirements'))
            ->filter(fn (array $requirement, string $code): bool => str_starts_with($code, 'KPS '));

        $this->assertCount(81, $requirements);
        $this->assertSame(104, $requirements->sum('count'));
        $this->assertSame(2, $requirements['KPS 1 / EP 3']['count']);
        $this->assertSame(3, $requirements['KPS 9 / EP 5']['count']);
        $this->assertSame(2, $requirements['KPS 10.1 / EP 2']['count']);
        $this->assertSame(2, $requirements['KPS 12 / EP 1']['count']);
        $this->assertSame(1, $requirements['KPS 16 / EP 2']['count']);

        $requirements->each(fn (array $requirement) => $this->assertDoesNotMatchRegularExpression(
            '/\[(?:W|O|S)\]/',
            $requirement['evidence'],
        ));
    }

    public function test_akp_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.AKP');

        $this->assertSame('MEDIS', config('accreditation.pokja_groups.AKP'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, '1.1', '1.2', '1.3', 2, '2.1', 3, '3.1', 4, 5, '5.1', '5.2', '5.3', '5.4', '5.5', '5.6', '5.7', 6],
            array_keys($standards),
        );
        $this->assertCount(18, $standards);
        $this->assertSame(65, array_sum($standards));
        $this->assertSame(5, $standards['5.2']);
    }

    public function test_pab_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.PAB');

        $this->assertSame('MEDIS', config('accreditation.pokja_groups.PAB'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, 2, 3, '3.1', '3.2', 4, 5, 6, '6.1', 7, '7.1', '7.2', '7.3', '7.4'],
            array_keys($standards),
        );
        $this->assertCount(14, $standards);
        $this->assertSame(38, array_sum($standards));
        $this->assertSame(3, $standards['3.2']);
    }

    public function test_hpk_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.HPK');

        $this->assertSame('MEDIS', config('accreditation.pokja_groups.HPK'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, '1.1', '1.2', '1.3', '1.4', '1.5', 2, '2.1', '2.2', 3, 4, '4.1', '4.2'],
            array_keys($standards),
        );
        $this->assertCount(13, $standards);
        $this->assertSame(39, array_sum($standards));
        $this->assertSame(5, $standards[2]);
    }

    public function test_ke_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.KE');

        $this->assertSame('MEDIS', config('accreditation.pokja_groups.KE'));
        $this->assertIsArray($standards);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_keys($standards));
        $this->assertCount(7, $standards);
        $this->assertSame(25, array_sum($standards));
        $this->assertSame(5, $standards[5]);
    }

    public function test_pap_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.PAP');

        $this->assertSame('MEDIS', config('accreditation.pokja_groups.PAP'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, '1.1', '1.2', 2, '2.1', '2.2', '2.3', '2.4', '2.5', '2.6', '2.7', 3, 4, 5],
            array_keys($standards),
        );
        $this->assertCount(14, $standards);
        $this->assertSame(52, array_sum($standards));
        $this->assertSame(6, $standards['2.4']);
    }

    public function test_pkpo_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.PKPO');

        $this->assertSame('MEDIS', config('accreditation.pokja_groups.PKPO'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, 2, 3, '3.1', '3.2', '3.3', 4, '4.1', 5, '5.1', 6, '6.1', 7, '7.1'],
            array_keys($standards),
        );
        $this->assertCount(14, $standards);
        $this->assertSame(51, array_sum($standards));
        $this->assertSame(7, $standards[5]);
    }

    public function test_pp_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.PP');

        $this->assertSame('MEDIS', config('accreditation.pokja_groups.PP'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, '1.1', '1.2', '1.3', 2, 3, '3.1', '3.2', '3.3', '3.4', '3.5', '3.6', '3.7', '3.8', '3.9', 4, '4.1', '4.2', '4.3', '4.4', '4.5'],
            array_keys($standards),
        );
        $this->assertCount(21, $standards);
        $this->assertSame(58, array_sum($standards));
        $this->assertSame(6, $standards['1.1']);
    }

    public function test_skp_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.SKP');

        $this->assertSame('MEDIS', config('accreditation.pokja_groups.SKP'));
        $this->assertIsArray($standards);
        $this->assertSame([1, 2, 3, '3.1', 4, 5, 6, '6.1'], array_keys($standards));
        $this->assertCount(8, $standards);
        $this->assertSame(24, array_sum($standards));
        $this->assertSame(4, $standards[4]);
    }

    public function test_kps_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.KPS');

        $this->assertSame('MANAJEMEN', config('accreditation.pokja_groups.KPS'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8, '8.1', 9, 10, '10.1', 11, 12, 13, 14, 15, 16, 17, 18, 19],
            array_keys($standards),
        );
        $this->assertCount(21, $standards);
        $this->assertSame(81, array_sum($standards));
        $this->assertSame(7, $standards[9]);
    }

    public function test_mfk_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.MFK');

        $this->assertSame('MANAJEMEN', config('accreditation.pokja_groups.MFK'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, 2, 3, 4, 5, '5.1', 6, 7, 8, '8.1', '8.2', '8.2.1', '8.3', 9, 10, 11],
            array_keys($standards),
        );
        $this->assertCount(16, $standards);
        $this->assertSame(68, array_sum($standards));
        $this->assertSame(6, $standards[6]);
    }

    public function test_mrmik_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.MRMIK');

        $this->assertSame('MANAJEMEN', config('accreditation.pokja_groups.MRMIK'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, 2, '2.1', '2.2', 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, '13.1'],
            array_keys($standards),
        );
        $this->assertCount(16, $standards);
        $this->assertSame(51, array_sum($standards));
        $this->assertSame(5, $standards[13]);
    }

    public function test_pmkp_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.PMKP');

        $this->assertSame('MANAJEMEN', config('accreditation.pokja_groups.PMKP'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, 2, 3, 4, '4.1', 5, 6, 7, 8, 9, 10, 11],
            array_keys($standards),
        );
        $this->assertCount(12, $standards);
        $this->assertSame(44, array_sum($standards));
        $this->assertSame(7, $standards[4]);
    }

    public function test_ppi_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.PPI');

        $this->assertSame('MANAJEMEN', config('accreditation.pokja_groups.PPI'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, '1.1', 2, 3, 4, '4.1', 5, 6, 7, '7.1', '7.2', 8, 9, 10, '10.1', 11, '11.1', 12, 13],
            array_keys($standards),
        );
        $this->assertCount(19, $standards);
        $this->assertSame(62, array_sum($standards));
        $this->assertSame(5, $standards['7.2']);
    }

    public function test_ppk_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.PPK');

        $this->assertSame('MANAJEMEN', config('accreditation.pokja_groups.PPK'));
        $this->assertIsArray($standards);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys($standards));
        $this->assertCount(6, $standards);
        $this->assertSame(23, array_sum($standards));
        $this->assertSame(6, $standards[6]);
    }

    public function test_prognas_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.PROGNAS');

        $this->assertSame('MANAJEMEN', config('accreditation.pokja_groups.PROGNAS'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, '1.1', 2, '2.1', '2.2', 3, 4, '4.1', 5, '5.1', 6, '6.1'],
            array_keys($standards),
        );
        $this->assertCount(12, $standards);
        $this->assertSame(43, array_sum($standards));
        $this->assertSame(6, $standards[3]);
    }

    public function test_tkrs_configuration_contains_the_exact_standard_and_ep_counts(): void
    {
        $standards = config('accreditation.drive_structures.TKRS');

        $this->assertSame('MANAJEMEN', config('accreditation.pokja_groups.TKRS'));
        $this->assertIsArray($standards);
        $this->assertSame(
            [1, 2, 3, '3.1', 4, 5, 6, 7, '7.1', 8, 9, 10, 11, 12, 13, 14, 15],
            array_keys($standards),
        );
        $this->assertCount(17, $standards);
        $this->assertSame(68, array_sum($standards));
        $this->assertSame(6, $standards[13]);
    }
}
