<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Hut;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvData = <<<'CSV'
club,website,guestbunks,hutname,pppn,dryingroom,gridref,location,lat,lng,caveclub
ABMSAC-Association of British Members of the Swiss Alpine Club,,,George Starkey,11,TRUE,NY 396160,"Patterdale, Patterdale, Cumbria",54.536333,-2.9378416,FALSE
Barrow Mountaineering & Ski Club,,,Coppermines Cottage,8,FALSE,SD 289986,"Coppermines Valley, CONISTON, Cumbria, LA21 8HP",54.3779,-3.094783,FALSE
Brathay Hall Trust,,,Shackleton Lodge,POA Sole occupancy,TRUE,NY 366027,"Shackleton Lodge, Brathay Hall, Ambleside Cumbria, LA22 0HP",54.416211,-2.9782001,FALSE
Carlisle Mountaineering Club,,,Newlands,7,FALSE,NY 229177,"Newlands Valley, Cumbria",54.548531,-3.19279,FALSE
Cleveland Mountaineering Club,,,Agnes Spencer Memorial Hut,8,TRUE,NY 391162,"Patterdale, Ullswater",54.537544,-2.9423531,FALSE
Fylde Mountaineering Club,,,Stair Hut,POA Sole occupancy,TRUE,NY 237210,"Stair Hut, Stair, KESWICK, Cumbria, CA12 5UF",54.579005,-3.1816129,FALSE
K Fellfarers,,,High House,POA Sole occupancy,TRUE,NY 235119,"High House, Borrowdale, KESWICK, Cumbria, CA12 5XJ",54.496835,-3.1816832,FALSE
Lancashire Caving & Climbing Club,https://www.lancashirecavingandclimbingclub.co.uk/,20,Tranearth,9,TRUE,SD 281957,"Tranearth, Torver, CONISTON, Cumbria, LA21 8BP",54.351177,-3.1077703,TRUE
Lancashire Mountaineering Club,,,The Loft adjacent to Blea Tarn House,8,FALSE,NY 295048,"The Loft adjacent to Blea Tarn House, Little Langdale, AMBLESIDE, Cumbria,LA22 9PG",54.433925,-3.0883511,FALSE
St Helens Mountaineering Club,,,Martin Brookman,8,FALSE,NY 235230,"Newlands Valley Road, Braithwaite, nr Keswick",54.596187,-3.1833496,FALSE
Wayfarers Walking Club,,,Robertson Lamb,10,TRUE,NY 304067,"Raw Head, Great Langdale, AMBLESIDE, Cumbria, LA22 9JS",54.450766,-3.0745323,FALSE
Yorkshire Mountaineering Club,,,"3 Irish Row, Coppermines Valley",8,TRUE,SD 292984,"3 Irish Row, Coppermines Valley, CONISTON, Cumbria, LA21 8HP",54.37673,-3.0913679,FALSE
Yorkshire Ramblers Club,,12,Low Hall Garth,10,TRUE,NY 309029,"Low Hall Garth, Little Langdale, AMBLESIDE, Cumbria, LA22 9NT",54.416587,-3.0655791,TRUE
Fylde Mountaineering Club,,,Little Langdale,POA Sole occupancy,TRUE,NY 315030,"2 New Houses, Little Langdale, AMBLESIDE, Cumbria, LA22 9PB",54.418089,-3.0558948,FALSE
Rucksack Club,,,High Moss,15,FALSE,SD 23740 96681,"High Moss Cottage, Seathwaite, BROUGHTON-IN-FURNESS, Cumbria, LA20 6EE",54.359926,-3.1750353,FALSE
Climbers' Club,,,R O Downes,12,FALSE,SK 249772,"Froggatt, Derbyshire",53.290761,-1.6278044,FALSE
Derbyshire Pennine Club,https://thedpc.co.uk/hut,13,Carlswark Cottage,8,FALSE,SK 232755,"Stoney Middleton, Peak District, North Derbyshire, S32 4TN",53.090327,-4.0559473,TRUE
Oread Mountaineering Club,,,Heathy Lea,8/6.5,FALSE,SK 273722,"One mile from Baslow, off main road A619 for Chesterfield, Close to Chatsworth, Birchen and Gardoms edges, Marked as Saw Mill on O.S. White Peak Map, Peak District",53.245362,-1.5938081,FALSE
The British Mountaineering Council,,,Don Whillans Memorial Hut,8,FALSE,SK 007622,"Rockhall Cottage, The Roaches",53.156737,-1.9924588,FALSE
Univ of London Mountaineering Club,,,Fallcliffe Cottage,9,FALSE,SK 240792,"Fall Cliff, Leam, Grindleford, HOPE VALLEY, Derbyshire, S32 2HL",53.308988,-1.6410006,FALSE
An Teallach MC,,,Strawberry Cottage,6.5,TRUE,NH 13197 20648,"Strawberry Cottage, West end of Loch Affric, on north side of the river Affric, opposite Athnamulloch",57.238295,-5.0970338,FALSE
Junior Mountaineering Club of Scotland,,,Coruisk Memorial,7.5,FALSE,NG 487197,"Loch Scavaig, ISLE OF SKYE",57.198957,-6.1620169,FALSE
Mill Cottage Trust,,,Mill Cottage Trust,10,TRUE,NH 84694 04800,"Mill Cottage, Feshie Bridge, KINGUSSIE, Inverness-Shire",57.119624,-3.9059472,FALSE
Mt Council of Scotland / BMC,,,Alex MacIntyre Memorial Hut,9,TRUE,NN 044612,"North Ballachulish, Onich, FORT WILLIAM, Inverness-Shire",56.700495,-5.1937743,FALSE
Mt Council of Scotland / BMC,,,Glen Brittle Memorial Hut,10,TRUE,NG 412216,"Glenbrittle, Carbost, ISLE OF SKYE, IV47 8TA",57.211901,-6.2886569,FALSE
Ochils Mountaineering Club,,,Inverardran Cottage,9,TRUE,NN 39190 25041,"Inverardran Cottage, CRIANLARICH, Perthshire, FK20 8QS",56.390195,-4.6065881,FALSE
Climbers' Club,,,Bosigran Count House,12,TRUE,SW 422365,"Bosigran Farm, Pendeen, PENZANCE, Cornwall, TR20 8YX",50.172574,-5.6127389,FALSE
Anabasis Mountaineering Club,,,Peter Llowarch Memorial Hut,5,FALSE,SH 700569,"Capel Curig, BETWS-Y-COED, Gwynedd",53.093546,-3.9430424,FALSE
Bowline Climbing Club,,,Dinorwic Mill Cottage,10,FALSE,SH 563625,"Dinorwic Mill Cottage, Brynrefail, Llanberis, CAERNARFON, Gwynedd",53.141448,-4.1521356,FALSE
Bromsgrove & Redditch Mt Club,,,Tremadog,POA,TRUE,SH 576405,"Tremadog, PORTHMADOG, Gwynedd",52.943333,-4.120558,FALSE
Ceunant Mountaineering Club,,,Tyn Lon,10,TRUE,SH 608582,"Nant Peris, CAERNARFON, Gwynedd, LL55 4UF",53.103946,-4.0826567,FALSE
Chester Mountaineering Club,,,Pen Ceunant Uchaf,7.5,TRUE,SH 581591,"Penceunant Uchaf, Llanberis, CAERNARFON, Gwynedd, LL55 4UW",53.109811,-4.1223373,FALSE
Climbers' Club,,,Cwm Glas Mawr,12,FALSE,SH 619569,Llanberis Pass,53.091758,-4.0621217,FALSE
Climbers' Club,,,Grange Old School House,12,FALSE,NY 251 176,"Grange, Borrowdale",54.54739,-3.1595745,FALSE
Climbers' Club,,,Helyg Hostel,12,TRUE,SH 694601,"Helyg Hostel, Capel Curig, BETWS-Y-COED, Gwynedd, LL24 0EU",53.122602,-3.9567324,FALSE
Climbers' Club,,,"R W Lloyd Hut, Ynys Ettws",12,TRUE,SH 624 568,Llanberis Pass,53.090327,-4.0559473,FALSE
Clogwyn Mountaineering Club,,,The Old Stable,8,FALSE,SH 619428,"Croesor Valley, Tremadog, PORTHMADOG, Gwynedd",52.964665,-4.056532,FALSE
Coventry Mountaineering Club,,,A T Reeve Memorial Hut,9,FALSE,SH 757078,"Aberderi, 3 Park Terrace, Corris, Machynlleth, Powys, SY20 9TD",52.65372,-3.8390602,FALSE
Gloucestershire Mountaineering Club,,,Cefn Goch,8.5,TRUE,SH 583625,"Cefn Goch Farm, Gallt Y Foel, Deiniolen, CAERNARFON, Gwynedd, LL55 3EF",53.140817,-4.1191668,FALSE
Gwydyr Mountain Club,,,Tan y Garth,9,TRUE,SH 737567,"Tan Y Garth, Capel Curig, BETWS-Y-COED, Gwynedd, LL24 0EB",53.092765,-3.8879134,FALSE
Junior Mountaineering Club of Scotland,,,Glanafon,8,FALSE,SH 623667,"18 Glanafon Street, Bethesda, BANGOR, Gwynedd",53.18031,-4.0641694,FALSE
Karabiner Mountaineering Club,,,Ty Powdwr,7,TRUE,SH 585609,"Dinorwic, Caernarfon, LL55 3ET",53.127183,-4.1156962,FALSE
Lancashire Caving & Climbing Club,https://www.lancashirecavingandclimbingclub.co.uk/,11,Dan y Mynydd,9,FALSE,SH 684454,"Tanygrisiau, Nr. Blaenau Ffestiniog, Gwynedd, LL41 3ST",52.988454,-3.9618383,FALSE
Lincoln Mountaineering Club,,,Ty Gwyn,8,FALSE,SH 580625,"Ty Gwyn, Deiniolen, CAERNARFON, Gwynedd",53.140787,-4.1242241,FALSE
London Mountaineering Club,,,Fronwydyr,8,TRUE,SH 606588,"Fronwydyr, Nant Peris, CAERNARFON, Gwynedd, LL55 4UN",53.108261,-4.0848337,FALSE
Merseyside Mountaineering Club,,,Cae'r Fran,7,TRUE,SH 570596,"Cae'r Fran, Capel Coch Road, Llanberis, CAERNARFON, Gwynedd, LL55 4SH",53.114165,-4.1376834,FALSE
Mountaineering Club of North Wales,,,Tal y Braich,6.5,TRUE,SH 700604,"Tal Y Braich, Capel Curig, BETWS-Y-COED, Gwynedd, LL24 0EU",53.125005,-3.9443324,FALSE
Mynydd Climbing Club,,,Blaen y Nant,8,TRUE,SH 738603,"Blaen y Nant, Crafnant Road, TREFRIW, Gwynedd, LL27 0JZ",53.12454,-3.8863395,FALSE
Northern Pennine Club,https://www.northernpennineclub.org.uk/,20,Greenclose House,5,TRUE,SD 721693,"Greenclose House, Clapham, Via Lancaster, LA2 8HW",54.118803,-2.4283318,TRUE
North London Mountaineering Club,,,Bryn Brethynau,5,FALSE,SH 737572,"Bryn Brethynau, Capel Curig, BETWS-Y-COED, Gwynedd",53.097137,-3.8872846,FALSE
Oread Mountaineering Club,,,Tan Yr Wyddfa,9,TRUE,SH 570527,"Tan Yr Wyddfa, Rhyd Ddu, CAERNARFON, Gwynedd, LL54 6TN",53.05248,-4.1345913,FALSE
Oxford Mountaineering Club,,,Cefn Garw,6,TRUE,SH 884518,"Cefn Garw, Pentrefoelas, BETWS-Y-COED, Gwynedd, LL24 0TA",53.052052,-3.6655878,FALSE
Pinnacle Club,,,Cwm Dyli,8,TRUE,SH 653541,"Cwm Dyli, Nant Gwynant, CAERNARFON, Gwynedd",53.067054,-4.0124891,FALSE
Rucksack Club,,,Beudy Mawr,8,TRUE,SH 616576,"Beudy Mawr, Nant Peris, CAERNARFON, Gwynedd, LL55 4UL",53.097096,-4.0693009,FALSE
Rugby Mountaineering Club,,,Cwm Eigiau Cottage,5,FALSE,SH 714638,"Cwm Eigiau Cottage, Cwm Eigiau",53.155981,-3.9249556,FALSE
Shrewsbury Mountaineering Club,,,Clwt y Bel,POA,TRUE,SH 592630,"Clwt y Bel, Deiniolen, CAERNARFON, Gwynedd",53.145725,-4.1067128,FALSE
Solihull Mountaineering Club,,,Bryn Golau,9,TRUE,SH 858148,"Bryn Golau, Dinas Mawddwy, MACHYNLLETH, Powys",52.723273,-3.6887497,FALSE
South Wales Mountaineering Club,,,Lletty Llwyd,10,TRUE,SH 580625,"Lletty Llwyd, Deiniolen, CAERNARFON, Gwynedd",53.140502,-4.1238297,FALSE
The Chamois Club Ltd,,,Chamois Mountaineering Centre,10,TRUE,SH532608,"Ceunant, Waunfawr, CAERNARFON, Gwynedd, LL55 4SA",53.124144,-4.1957241,FALSE
The Mountain Club - Stafford,,,Bryn Hafod,10,FALSE,SH 853194,"Bryn Hafod, Dinas Mawddwy, MACHYNLLETH, Powys, SY20 9JG",52.759739,-3.7004846,FALSE
Univ of London Mountaineering Club,,,Caseg Fraith,9,FALSE,SH 684601,"Caseg Fraith, Capel Curig, BETWS-Y-COED, Gwynedd",53.121926,-3.9682927,FALSE
Vagabond Mountaineering Club,,,Pant y Fron,3,FALSE,SH 606588,"Pant Y Fron, Nant Peris, CAERNARFON, Gwynedd, LL55 4UN",53.108828,-4.083569,FALSE
Wellingborough Mountaineering Club,,,Gefnan Cottage,POA,FALSE,SH 607654,"Gefnan Cottage, 3 Gefnan, Mynydd Landegai, BANGOR, Gwynedd, LL57 4DJ",53.167281,-4.0843496,FALSE
Wessex Mountaineering Club,,,Pen Y Clogwyn,6,FALSE,SH 795556,"Pen Y Clogwyn, BETWS-Y-COED, Gwynedd",53.08386,-3.8003836,FALSE
Wolverhampton Mountaineering Club,,,Tal y Braich,8,FALSE,SH 587625,"Tal y Braich, Deiniolen, CAERNARFON, Gwynedd",53.140496,-4.1129846,FALSE
Yorkshire Ramblers Club,https://www.yrc.org.uk/,16,Lowstern,12,TRUE,SD 736691,"Lowstern, Clapham, SETTLE, North Yorkshire",54.116675,-2.404479,TRUE
Cwellyn Arms,,,Big Bunkhouse,15,TRUE,SH 566540,"Cwellyn Arms, Rhyd Ddu, At the foot of Snowdon, Gwynedd, LL54 6TL",53.064818,-4.141928,FALSE
Crashpad Lodges,,,Yr Helfa Bunkhouse ,17,FALSE,SH 58353 57491,"Yr Helfa, Llanberis, Caernarfon, Gwynedd. LL55 4UW",53.095769,-4.1168691,FALSE
Pentre Bach Bunkhouse,,,Pentre Bach Bunkhouse,12,TRUE,SH 53060 57944,near Waunfawr ,53.098413,-4.1960573,FALSE
Snowdon Holidays,,,Snowdon Bunkhouse,12,FALSE,SH 61322 57764,Gwastadnant,53.099008,-4.0726772,FALSE
Snowdon Holidays,,,Snowdon House,POA,TRUE,SH 61302 57773,Gwastadnant,53.099079,-4.072978,FALSE
Jesse James bunkhouse,,,Jesse James bunkhouse,POA,FALSE,SH 566 638,"Jesse James Bunkhouse, Caernarfon, Gwynedd LL55 3DA",53.152538,-4.1453447,FALSE
Bens Bunkhouse,,,Bens Bunkhouse,15,TRUE,SH 597588 ,"Bens Bunkhouse, Nant Peris, Llanberis. LL55 4UD",53.108197,-4.0973856,FALSE
The Eagles Bunkhouse,,,The Eagles,20,TRUE,SH 78946 50574,"The Eagles,  Penmachno,  Conwy. LL24 0UG",53.0387,-3.8069028,FALSE
Bristol Exploration Club,https://www.bec-cave.org.uk,20,The Belfry,5,TRUE,,,51.251768,-2.65734,TRUE
Cerberus Spelaeological Society,https://www.cerberusspeleo.org.uk,31,,5,TRUE,,,51.222916,-2.5018497,TRUE
Mendip Caving Group,https://www.mendipcavinggroup.org.uk,30,Nordrach Cottage,6,FALSE,,,51.301561,-2.697496,TRUE
Mendip Nature Research Committee,https://www.mnrc.org.uk,21,,6,TRUE,,,51.24206,-2.589527,TRUE
Shepton Mallet Caving Club,https://www.shepton.org.uk,25,,4.5,TRUE,,,51.251445,-2.654848,TRUE
Wessex Cave Club,https://www.wessex-cave-club.org,44,Upper Pits,6,TRUE,,,51.255311,-2.665869,TRUE
Bradford Pothole Club,https://www.bpc-cave.org.uk/,15,,6,TRUE,,,54.1454232,-2.2821255,TRUE
Craven Pothole Club,https://www.cravenpotholeclub.org,12,,6,TRUE,,,54.149744,-2.295881,TRUE
Northern Pennine Club,https://www.northernpennineclub.org.uk,20,,5,TRUE,,,54.118803,-2.4283318,TRUE
Red Rose CPC,https://www.rrcpc.org.uk,20,,5,TRUE,,,54.227623,-2.518617,TRUE
YSS,https://www.yssuk.com ,32,,5,TRUE,,,54.1210481,-2.2939627,TRUE
Orpheus Caving Club,https://www.orpheuscavingclub.org.uk,18,,6,TRUE,,,53.162152,-1.783156,TRUE
Technical Speleological Group,https://www.tsgcaving.co.uk,18,The Chapel,5,TRUE,,,53.343435,-1.775418,TRUE
Chelsea Spelaeological Society,https://www.chelseaspelaeo.org.uk/,16,Whitewalls,5,TRUE,,,51.835127,-3.156622,TRUE
Croydon Caving Club,https://www.croydoncavingclub.org.uk,15,Godre Pentre,5,TRUE,,,51.808993,-3.554072,TRUE
South Wales Caving Club,https://www.swcc.org.uk,30,,9,TRUE,,"1-10 Powell St, Penwyllt, Pen-y-Cae, Swansea SA9 1GQ",51.825775,-3.661131,TRUE
Westminster Speleological Group,https://www.wsg.org.uk,18,,4,TRUE,,,51.752367,-3.544872,TRUE
Devon Speleological Society,https://www.devonss.org.uk,16,,11,TRUE,,,50.480946,-3.783131,TRUE
Grampian Speleological Group,https://www.gsg.org.uk/,20,,8,TRUE,,,58.056469,-5.028023,TRUE
William Pengelly Cave Studies Trust,https://www.pengellytrust.org/,25,,10,TRUE,,,50.484378,-3.77133,TRUE
University of Leeds,https://sport.leeds.ac.uk/facilities/outdoors/,23,Selside,POA Sole occupancy,TRUE,,,54.177516,-2.3354833,FALSE
University of Leeds,https://sport.leeds.ac.uk/facilities/outdoors/,36,Dalehead,POA Sole occupancy,TRUE,,,54.395571,-3.16982,FALSE
CSV;

        // Parse CSV
        $rows = array_map('str_getcsv', explode("\n", trim($csvData)));
        $header = array_shift($rows);

        foreach ($rows as $row) {
            // Combine header with row data to create an associative array
            // Handlling potential mismatch in column count
            if (count($row) !== count($header)) {
                continue;
            }
            $data = array_combine($header, $row);

            $clubName = trim($data['club']);
            if (empty($clubName)) {
                continue;
            }

            $club = null;
            // If they are a caving club then create or find the club
            if (isset($data['caveclub']) && strtoupper($data['caveclub']) === 'TRUE') {
                $slug = Str::slug($clubName);
                $club = Club::where('slug', $slug)->first();

                if (!$club) {
                    $club = Club::firstOrCreate(
                        ['name' => $clubName],
                        [
                            'slug' => $slug,
                            'is_active' => true,
                        ]
                    );
                }
            }

            // Map fields
            $hutName = !empty($data['hutname']) ? $data['hutname'] : $clubName.' Hut';
            $website = $data['website'];

            // Ensure website has https:// prefix
            if (!empty($website) && !preg_match('~^(?:f|ht)tps?://~i', $website)) {
                $website = 'https://'.$website;
            }

            $guestBunks = $data['guestbunks'];
            $pppn = $data['pppn'];
            $dryingRoom = $data['dryingroom'] === 'TRUE' ? 'Yes' : 'No';
            $gridRef = $data['gridref'];
            $location = $data['location'];
            $lat = is_numeric($data['lat']) ? (float) $data['lat'] : null;
            $lng = is_numeric($data['lng']) ? (float) $data['lng'] : null;
            $caveClub = $data['caveclub'] === 'TRUE' ? 'Yes' : 'No';

            // Build MarkDown Description
            $description = [];
            if (!empty($guestBunks)) {
                $description[] = "**Guest Bunks:** $guestBunks";
            }
            if (!empty($pppn)) {
                $description[] = "**Price Per Person Per Night:** $pppn";
            }
            if (!empty($data['dryingroom'])) {
                $description[] = "**Drying Room:** $dryingRoom";
            }
            if (!empty($gridRef)) {
                $description[] = "**Grid Ref:** $gridRef";
            }
            if (!empty($location)) {
                $description[] = "**Location:** $location";
            }
            if (!empty($data['caveclub'])) {
                $description[] = "**Cave Club:** $caveClub";
            }

            $descriptionString = implode("\n\n", $description);

            // Create Hut - updateOrCreate to be idempotent
            Hut::updateOrCreate(
                ['name' => $hutName],
                [
                    'club_id' => $club ? $club->id : null,
                    'description' => $descriptionString,
                    'external_url' => $website,
                    'location_lat' => $lat,
                    'location_lng' => $lng,
                ]
            );
        }
    }
}
