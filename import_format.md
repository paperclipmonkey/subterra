# Cave Import Format

You can import caves into Subterra using the `artisan import:caves` command. The command supports CSV and TSV files.

## Command Usage

```bash
php artisan import:caves path/to/file.csv
# or
php artisan import:caves path/to/file.tsv
```

## File Format

The file should be a CSV (comma-separated) or TSV (tab-separated) file with a header row. The following columns are supported:

| Column Name | Description | Required | Example |
| :--- | :--- | :--- | :--- |
| `name` | Name of the cave | **Yes** | `Ogof Ffynnon Ddu` |
| `system` | Name of the cave system. If empty, defaults to cave name. | No | `Ogof Ffynnon Ddu` |
| `length` | Length of the system/cave in meters. | No | `60600` |
| `depth` | Vertical range/depth in meters. | No | `308` |
| `latitude` | Latitude (decimal degrees). | No | `51.8232` |
| `longitude` | Longitude (decimal degrees). | No | `-3.6732` |
| `altitude` | Altitude in meters. | No | `350` |
| `location_name` | Name of the town or region. | No | `Penwyllt` |
| `location_country` | Country name. Defaults to 'United Kingdom'. | No | `Wales` |
| `description` | Main description of the cave. | No | `A major cave system...` |
| `notes` | Additional notes (appended to description). | No | `Key available from...` |
| `references` | External references (appended to description). | No | `See CoSW page 123` |
| `access_info` | Access information text. | No | `Permit required.` |
| `tags` | Comma-separated list of tags. | No | `Sporting, Wet, SRT` |

## Example CSV

```csv
name,system,length,depth,latitude,longitude,location_name,tags
Ogof Ffynnon Ddu 1,Ogof Ffynnon Ddu,60600,308,51.8232,-3.6732,Penwyllt,"Sporting,Wet"
Agen Allwedd,Agen Allwedd,32000,160,,,Llangattock,"Drought,Permit"
```
