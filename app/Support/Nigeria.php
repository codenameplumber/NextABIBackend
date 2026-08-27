<?php

namespace App\Support;

/**
 * Nigeria — states, local governments, and the shape of a registration.
 *
 * A static map rather than a table. This is reference data that changes when
 * the National Assembly creates a local government, which is to say roughly
 * never — putting it in the database would mean a migration, a seeder, a query
 * on every registration render, and a foreign key that buys nothing.
 *
 * 36 states plus the Federal Capital Territory; 774 local government areas,
 * which is the full statutory list. Ordered alphabetically within each state so
 * a dropdown is scannable without sorting at render time.
 *
 * WORTH VERIFYING before launch: a handful of LGAs are commonly written two
 * ways (Egbado North / Yewa North in Ogun, Yakuur / Yakurr in Cross River).
 * Both spellings are in circulation; the ones here follow the INEC listing.
 * If a buyer's document disagrees with the dropdown, the document wins and the
 * list should be corrected here rather than worked around at the call site.
 */
final class Nigeria
{
    /** @var array<string, string[]> */
    public const STATES = [
        'Abia' => [
            'Aba North', 'Aba South', 'Arochukwu', 'Bende', 'Ikwuano',
            'Isiala Ngwa North', 'Isiala Ngwa South', 'Isuikwuato', 'Obi Ngwa',
            'Ohafia', 'Osisioma', 'Ugwunagbo', 'Ukwa East', 'Ukwa West',
            'Umuahia North', 'Umuahia South', 'Umu Nneochi',
        ],
        'Adamawa' => [
            'Demsa', 'Fufore', 'Ganye', 'Gayuk', 'Gombi', 'Grie', 'Hong', 'Jada',
            'Lamurde', 'Madagali', 'Maiha', 'Mayo Belwa', 'Michika', 'Mubi North',
            'Mubi South', 'Numan', 'Shelleng', 'Song', 'Toungo', 'Yola North',
            'Yola South',
        ],
        'Akwa Ibom' => [
            'Abak', 'Eastern Obolo', 'Eket', 'Esit Eket', 'Essien Udim',
            'Etim Ekpo', 'Etinan', 'Ibeno', 'Ibesikpo Asutan', 'Ibiono-Ibom',
            'Ika', 'Ikono', 'Ikot Abasi', 'Ikot Ekpene', 'Ini', 'Itu', 'Mbo',
            'Mkpat-Enin', 'Nsit-Atai', 'Nsit-Ibom', 'Nsit-Ubium', 'Obot Akara',
            'Okobo', 'Onna', 'Oron', 'Oruk Anam', 'Udung-Uko', 'Ukanafun',
            'Uruan', 'Urue-Offong/Oruko', 'Uyo',
        ],
        'Anambra' => [
            'Aguata', 'Anambra East', 'Anambra West', 'Anaocha', 'Awka North',
            'Awka South', 'Ayamelum', 'Dunukofia', 'Ekwusigo', 'Idemili North',
            'Idemili South', 'Ihiala', 'Njikoka', 'Nnewi North', 'Nnewi South',
            'Ogbaru', 'Onitsha North', 'Onitsha South', 'Orumba North',
            'Orumba South', 'Oyi',
        ],
        'Bauchi' => [
            'Alkaleri', 'Bauchi', 'Bogoro', 'Damban', 'Darazo', 'Dass', 'Gamawa',
            'Ganjuwa', 'Giade', 'Itas/Gadau', "Jama'are", 'Katagum', 'Kirfi',
            'Misau', 'Ningi', 'Shira', 'Tafawa Balewa', 'Toro', 'Warji', 'Zaki',
        ],
        'Bayelsa' => [
            'Brass', 'Ekeremor', 'Kolokuma/Opokuma', 'Nembe', 'Ogbia', 'Sagbama',
            'Southern Ijaw', 'Yenagoa',
        ],
        'Benue' => [
            'Ado', 'Agatu', 'Apa', 'Buruku', 'Gboko', 'Guma', 'Gwer East',
            'Gwer West', 'Katsina-Ala', 'Konshisha', 'Kwande', 'Logo', 'Makurdi',
            'Obi', 'Ogbadibo', 'Ohimini', 'Oju', 'Okpokwu', 'Otukpo', 'Tarka',
            'Ukum', 'Ushongo', 'Vandeikya',
        ],
        'Borno' => [
            'Abadam', 'Askira/Uba', 'Bama', 'Bayo', 'Biu', 'Chibok', 'Damboa',
            'Dikwa', 'Gubio', 'Guzamala', 'Gwoza', 'Hawul', 'Jere', 'Kaga',
            'Kala/Balge', 'Konduga', 'Kukawa', 'Kwaya Kusar', 'Mafa', 'Magumeri',
            'Maiduguri', 'Marte', 'Mobbar', 'Monguno', 'Ngala', 'Nganzai', 'Shani',
        ],
        'Cross River' => [
            'Abi', 'Akamkpa', 'Akpabuyo', 'Bakassi', 'Bekwarra', 'Biase', 'Boki',
            'Calabar Municipal', 'Calabar South', 'Etung', 'Ikom', 'Obanliku',
            'Obubra', 'Obudu', 'Odukpani', 'Ogoja', 'Yakuur', 'Yala',
        ],
        'Delta' => [
            'Aniocha North', 'Aniocha South', 'Bomadi', 'Burutu', 'Ethiope East',
            'Ethiope West', 'Ika North East', 'Ika South', 'Isoko North',
            'Isoko South', 'Ndokwa East', 'Ndokwa West', 'Okpe', 'Oshimili North',
            'Oshimili South', 'Patani', 'Sapele', 'Udu', 'Ughelli North',
            'Ughelli South', 'Ukwuani', 'Uvwie', 'Warri North', 'Warri South',
            'Warri South West',
        ],
        'Ebonyi' => [
            'Abakaliki', 'Afikpo North', 'Afikpo South', 'Ebonyi', 'Ezza North',
            'Ezza South', 'Ikwo', 'Ishielu', 'Ivo', 'Izzi', 'Ohaozara', 'Ohaukwu',
            'Onicha',
        ],
        'Edo' => [
            'Akoko-Edo', 'Egor', 'Esan Central', 'Esan North-East',
            'Esan South-East', 'Esan West', 'Etsako Central', 'Etsako East',
            'Etsako West', 'Igueben', 'Ikpoba Okha', 'Orhionmwon', 'Oredo',
            'Ovia North-East', 'Ovia South-West', 'Owan East', 'Owan West',
            'Uhunmwonde',
        ],
        'Ekiti' => [
            'Ado Ekiti', 'Efon', 'Ekiti East', 'Ekiti South-West', 'Ekiti West',
            'Emure', 'Gbonyin', 'Ido Osi', 'Ijero', 'Ikere', 'Ikole', 'Ilejemeje',
            'Irepodun/Ifelodun', 'Ise/Orun', 'Moba', 'Oye',
        ],
        'Enugu' => [
            'Aninri', 'Awgu', 'Enugu East', 'Enugu North', 'Enugu South', 'Ezeagu',
            'Igbo Etiti', 'Igbo Eze North', 'Igbo Eze South', 'Isi Uzo',
            'Nkanu East', 'Nkanu West', 'Nsukka', 'Oji River', 'Udenu', 'Udi',
            'Uzo Uwani',
        ],
        'Gombe' => [
            'Akko', 'Balanga', 'Billiri', 'Dukku', 'Funakaye', 'Gombe', 'Kaltungo',
            'Kwami', 'Nafada', 'Shongom', 'Yamaltu/Deba',
        ],
        'Imo' => [
            'Aboh Mbaise', 'Ahiazu Mbaise', 'Ehime Mbano', 'Ezinihitte',
            'Ideato North', 'Ideato South', 'Ihitte/Uboma', 'Ikeduru',
            'Isiala Mbano', 'Isu', 'Mbaitoli', 'Ngor Okpala', 'Njaba', 'Nkwerre',
            'Nwangele', 'Obowo', 'Oguta', 'Ohaji/Egbema', 'Okigwe', 'Orlu', 'Orsu',
            'Oru East', 'Oru West', 'Owerri Municipal', 'Owerri North',
            'Owerri West', 'Unuimo',
        ],
        'Jigawa' => [
            'Auyo', 'Babura', 'Biriniwa', 'Birnin Kudu', 'Buji', 'Dutse',
            'Gagarawa', 'Garki', 'Gumel', 'Guri', 'Gwaram', 'Gwiwa', 'Hadejia',
            'Jahun', 'Kafin Hausa', 'Kaugama', 'Kazaure', 'Kiri Kasama', 'Kiyawa',
            'Maigatari', 'Malam Madori', 'Miga', 'Ringim', 'Roni',
            'Sule Tankarkar', 'Taura', 'Yankwashi',
        ],
        'Kaduna' => [
            'Birnin Gwari', 'Chikun', 'Giwa', 'Igabi', 'Ikara', 'Jaba', "Jema'a",
            'Kachia', 'Kaduna North', 'Kaduna South', 'Kagarko', 'Kajuru', 'Kaura',
            'Kauru', 'Kubau', 'Kudan', 'Lere', 'Makarfi', 'Sabon Gari', 'Sanga',
            'Soba', 'Zangon Kataf', 'Zaria',
        ],
        'Kano' => [
            'Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Bichi', 'Bunkure', 'Dala',
            'Dambatta', 'Dawakin Kudu', 'Dawakin Tofa', 'Doguwa', 'Fagge',
            'Gabasawa', 'Garko', 'Garun Mallam', 'Gaya', 'Gezawa', 'Gwale',
            'Gwarzo', 'Kabo', 'Kano Municipal', 'Karaye', 'Kibiya', 'Kiru',
            'Kumbotso', 'Kunchi', 'Kura', 'Madobi', 'Makoda', 'Minjibir',
            'Nasarawa', 'Rano', 'Rimin Gado', 'Rogo', 'Shanono', 'Sumaila',
            'Takai', 'Tarauni', 'Tofa', 'Tsanyawa', 'Tudun Wada', 'Ungogo',
            'Warawa', 'Wudil',
        ],
        'Katsina' => [
            'Bakori', 'Batagarawa', 'Batsari', 'Baure', 'Bindawa', 'Charanchi',
            'Dan Musa', 'Dandume', 'Danja', 'Daura', 'Dutsi', 'Dutsin Ma',
            'Faskari', 'Funtua', 'Ingawa', 'Jibia', 'Kafur', 'Kaita', 'Kankara',
            'Kankia', 'Katsina', 'Kurfi', 'Kusada', "Mai'Adua", 'Malumfashi',
            'Mani', 'Mashi', 'Matazu', 'Musawa', 'Rimi', 'Sabuwa', 'Safana',
            'Sandamu', 'Zango',
        ],
        'Kebbi' => [
            'Aleiro', 'Arewa Dandi', 'Argungu', 'Augie', 'Bagudo', 'Birnin Kebbi',
            'Bunza', 'Dandi', 'Fakai', 'Gwandu', 'Jega', 'Kalgo', 'Koko/Besse',
            'Maiyama', 'Ngaski', 'Sakaba', 'Shanga', 'Suru', 'Wasagu/Danko',
            'Yauri', 'Zuru',
        ],
        'Kogi' => [
            'Adavi', 'Ajaokuta', 'Ankpa', 'Bassa', 'Dekina', 'Ibaji', 'Idah',
            'Igalamela Odolu', 'Ijumu', 'Kabba/Bunu', 'Kogi', 'Lokoja',
            'Mopa Muro', 'Ofu', 'Ogori/Magongo', 'Okehi', 'Okene', 'Olamaboro',
            'Omala', 'Yagba East', 'Yagba West',
        ],
        'Kwara' => [
            'Asa', 'Baruten', 'Edu', 'Ekiti', 'Ifelodun', 'Ilorin East',
            'Ilorin South', 'Ilorin West', 'Irepodun', 'Isin', 'Kaiama', 'Moro',
            'Offa', 'Oke Ero', 'Oyun', 'Patigi',
        ],
        'Lagos' => [
            'Agege', 'Ajeromi-Ifelodun', 'Alimosho', 'Amuwo-Odofin', 'Apapa',
            'Badagry', 'Epe', 'Eti-Osa', 'Ibeju-Lekki', 'Ifako-Ijaiye', 'Ikeja',
            'Ikorodu', 'Kosofe', 'Lagos Island', 'Lagos Mainland', 'Mushin', 'Ojo',
            'Oshodi-Isolo', 'Shomolu', 'Surulere',
        ],
        'Nasarawa' => [
            'Akwanga', 'Awe', 'Doma', 'Karu', 'Keana', 'Keffi', 'Kokona', 'Lafia',
            'Nasarawa', 'Nasarawa Egon', 'Obi', 'Toto', 'Wamba',
        ],
        'Niger' => [
            'Agaie', 'Agwara', 'Bida', 'Borgu', 'Bosso', 'Chanchaga', 'Edati',
            'Gbako', 'Gurara', 'Katcha', 'Kontagora', 'Lapai', 'Lavun', 'Magama',
            'Mariga', 'Mashegu', 'Mokwa', 'Munya', 'Paikoro', 'Rafi', 'Rijau',
            'Shiroro', 'Suleja', 'Tafa', 'Wushishi',
        ],
        'Ogun' => [
            'Abeokuta North', 'Abeokuta South', 'Ado-Odo/Ota', 'Ewekoro', 'Ifo',
            'Ijebu East', 'Ijebu North', 'Ijebu North East', 'Ijebu Ode',
            'Ikenne', 'Imeko Afon', 'Ipokia', 'Obafemi Owode', 'Odeda',
            'Odogbolu', 'Ogun Waterside', 'Remo North', 'Shagamu', 'Yewa North',
            'Yewa South',
        ],
        'Ondo' => [
            'Akoko North-East', 'Akoko North-West', 'Akoko South-East',
            'Akoko South-West', 'Akure North', 'Akure South', 'Ese Odo', 'Idanre',
            'Ifedore', 'Ilaje', 'Ile Oluji/Okeigbo', 'Irele', 'Odigbo',
            'Okitipupa', 'Ondo East', 'Ondo West', 'Ose', 'Owo',
        ],
        'Osun' => [
            'Aiyedaade', 'Aiyedire', 'Atakumosa East', 'Atakumosa West',
            'Boluwaduro', 'Boripe', 'Ede North', 'Ede South', 'Egbedore', 'Ejigbo',
            'Ife Central', 'Ife East', 'Ife North', 'Ife South', 'Ifedayo',
            'Ifelodun', 'Ila', 'Ilesa East', 'Ilesa West', 'Irepodun', 'Irewole',
            'Isokan', 'Iwo', 'Obokun', 'Odo Otin', 'Ola Oluwa', 'Olorunda',
            'Oriade', 'Orolu', 'Osogbo',
        ],
        'Oyo' => [
            'Afijio', 'Akinyele', 'Atiba', 'Atisbo', 'Egbeda', 'Ibadan North',
            'Ibadan North-East', 'Ibadan North-West', 'Ibadan South-East',
            'Ibadan South-West', 'Ibarapa Central', 'Ibarapa East',
            'Ibarapa North', 'Ido', 'Irepo', 'Iseyin', 'Itesiwaju', 'Iwajowa',
            'Kajola', 'Lagelu', 'Ogbomosho North', 'Ogbomosho South', 'Ogo Oluwa',
            'Olorunsogo', 'Oluyole', 'Ona Ara', 'Orelope', 'Ori Ire', 'Oyo East',
            'Oyo West', 'Saki East', 'Saki West', 'Surulere',
        ],
        'Plateau' => [
            'Barkin Ladi', 'Bassa', 'Bokkos', 'Jos East', 'Jos North', 'Jos South',
            'Kanam', 'Kanke', 'Langtang North', 'Langtang South', 'Mangu',
            'Mikang', 'Pankshin', "Qua'an Pan", 'Riyom', 'Shendam', 'Wase',
        ],
        'Rivers' => [
            'Abua/Odual', 'Ahoada East', 'Ahoada West', 'Akuku-Toru', 'Andoni',
            'Asari-Toru', 'Bonny', 'Degema', 'Eleme', 'Emohua', 'Etche', 'Gokana',
            'Ikwerre', 'Khana', 'Obio/Akpor', 'Ogba/Egbema/Ndoni', 'Ogu/Bolo',
            'Okrika', 'Omuma', 'Opobo/Nkoro', 'Oyigbo', 'Port Harcourt', 'Tai',
        ],
        'Sokoto' => [
            'Binji', 'Bodinga', 'Dange Shuni', 'Gada', 'Goronyo', 'Gudu',
            'Gwadabawa', 'Illela', 'Isa', 'Kebbe', 'Kware', 'Rabah', 'Sabon Birni',
            'Shagari', 'Silame', 'Sokoto North', 'Sokoto South', 'Tambuwal',
            'Tangaza', 'Tureta', 'Wamako', 'Wurno', 'Yabo',
        ],
        'Taraba' => [
            'Ardo Kola', 'Bali', 'Donga', 'Gashaka', 'Gassol', 'Ibi', 'Jalingo',
            'Karim Lamido', 'Kumi', 'Lau', 'Sardauna', 'Takum', 'Ussa', 'Wukari',
            'Yorro', 'Zing',
        ],
        'Yobe' => [
            'Bade', 'Bursari', 'Damaturu', 'Fika', 'Fune', 'Geidam', 'Gujba',
            'Gulani', 'Jakusko', 'Karasuwa', 'Machina', 'Nangere', 'Nguru',
            'Potiskum', 'Tarmuwa', 'Yunusari', 'Yusufari',
        ],
        'Zamfara' => [
            'Anka', 'Bakura', 'Birnin Magaji/Kiyaw', 'Bukkuyum', 'Bungudu',
            'Gummi', 'Gusau', 'Kaura Namoda', 'Maradun', 'Maru', 'Shinkafi',
            'Talata Mafara', 'Tsafe', 'Zurmi',
        ],
        'Federal Capital Territory' => [
            'Abaji', 'Bwari', 'Gwagwalada', 'Kuje', 'Kwali',
            'Municipal Area Council',
        ],
    ];

    /**
     * Income brackets, in naira per year.
     *
     * Bands rather than a figure. People decline to state a salary and guess
     * when forced to, so a band is both likelier to be answered and likelier to
     * be true. The stored value is the label — it is a category, not a number
     * to compute with, and treating it as one invites a false average.
     */
    public const INCOME_BRACKETS = [
        'under-2m' => 'Under ₦2 million',
        '2m-5m' => '₦2 – 5 million',
        '5m-10m' => '₦5 – 10 million',
        '10m-25m' => '₦10 – 25 million',
        '25m-50m' => '₦25 – 50 million',
        'over-50m' => 'Above ₦50 million',
        'undisclosed' => 'Prefer not to say',
    ];

    /**
     * Occupation groups.
     *
     * Deliberately coarse. A free-text occupation field produces four hundred
     * spellings of "businessman" and cannot be grouped afterwards.
     */
    public const OCCUPATIONS = [
        'business-owner' => 'Business owner / Entrepreneur',
        'salaried' => 'Salaried professional',
        'civil-service' => 'Civil / Public service',
        'medical' => 'Medical / Healthcare',
        'legal' => 'Legal',
        'engineering' => 'Engineering / Technology',
        'finance' => 'Banking / Finance',
        'education' => 'Education',
        'oil-gas' => 'Oil & Gas',
        'trade' => 'Trading / Commerce',
        'agriculture' => 'Agriculture',
        'creative' => 'Creative / Media',
        'diaspora' => 'Working abroad',
        'retired' => 'Retired',
        'other' => 'Other',
    ];

    /**
     * Where did you hear about us.
     *
     * This is the marketing attribution axis, and it is a different question
     * from which realtor brought them — a buyer can find you through a radio
     * advert and still be signed up by a realtor. Asked on both the
     * questionnaire and the registration, because the two capture different
     * people at different moments.
     *
     * Keys are stable; labels can be reworded without orphaning history.
     */
    public const REFERRAL_SOURCES = [
        'realtor' => 'A realtor contacted me',
        'friend' => 'Friend or family',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'x' => 'X (Twitter)',
        'tiktok' => 'TikTok',
        'linkedin' => 'LinkedIn',
        'google' => 'Google search',
        'radio' => 'Radio',
        'billboard' => 'Billboard / Outdoor',
        'newspaper' => 'Newspaper',
        'event' => 'An event or exhibition',
        'church-mosque' => 'Church or mosque',
        'existing-client' => 'I am already a client',
        'other' => 'Somewhere else',
    ];

    /** @return string[] */
    public static function stateNames(): array
    {
        return array_keys(self::STATES);
    }

    /** @return string[] */
    public static function lgas(?string $state): array
    {
        return self::STATES[$state] ?? [];
    }

    public static function isState(?string $state): bool
    {
        return $state !== null && array_key_exists($state, self::STATES);
    }

    /** Both halves have to agree — a Lagos buyer cannot live in Bwari. */
    public static function isLga(?string $state, ?string $lga): bool
    {
        return $lga !== null && in_array($lga, self::lgas($state), true);
    }

    public static function incomeLabel(?string $key): ?string
    {
        return self::INCOME_BRACKETS[$key] ?? null;
    }

    public static function occupationLabel(?string $key): ?string
    {
        return self::OCCUPATIONS[$key] ?? null;
    }

    public static function sourceLabel(?string $key): ?string
    {
        return self::REFERRAL_SOURCES[$key] ?? null;
    }

    /** Sanity check used by the test suite: the statutory total is 774. */
    public static function lgaCount(): int
    {
        return array_sum(array_map('count', self::STATES));
    }
}
