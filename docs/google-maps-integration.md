# Google Maps Integration Documentation

## Overview
This implementation adds Google Maps integration to both landlord and customer interfaces, allowing for better property location management and visualization.

## Features

### For Landlords
- Interactive map interface for property location selection
- Current location detection using browser geolocation
- Address search with Google Places autocomplete
- Drag-and-drop marker placement
- Automatic address formatting
- Location coordinates storage

### For Customers
- Property location visualization on a map
- Interactive map with property marker
- Address display
- Nearby points of interest
- Clean and modern UI

## Database Changes
The following changes were made to the `houses` table:
```sql
ALTER TABLE houses 
ADD COLUMN latitude DECIMAL(10,8) NOT NULL,
ADD COLUMN longitude DECIMAL(11,8) NOT NULL,
ADD COLUMN address TEXT,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

## Implementation Files

### 1. Landlord Side
- `smart_landlords/add_property.php`: Property form with map integration
- `smart_landlords/process_property.php`: Property processing with location handling
- `smart_landlords/database/upgrade.sql`: Database migration script

### 2. Customer Side
- `smart_rental/property-details.php`: Property details page with map display

## Setup Instructions

1. Get a Google Maps API key from Google Cloud Console
2. Replace `YOUR_GOOGLE_MAPS_API_KEY` in:
   - `smart_landlords/add_property.php`
   - `smart_rental/property-details.php`
3. Run the database upgrade script to add new columns
4. Ensure the following libraries are included:
   - Bootstrap 5.3.0
   - Font Awesome 6.0.0
   - Google Maps JavaScript API
   - Google Places API

## Security Considerations
- All location data is stored securely in the database
- Input validation is performed on both client and server side
- Geolocation access requires user permission
- API keys should be kept secure and not exposed in client-side code

## Usage

### For Landlords
1. When adding a new property:
   - Click on the map to place a marker
   - Use the address search to find locations
   - Click "Use Current Location" to get your current position
   - Drag the marker to fine-tune the location
   - The address will be automatically updated

### For Customers
1. When viewing property details:
   - The property location will be displayed on an interactive map
   - Click the marker to see property information
   - The map shows the exact location of the property
   - The address is displayed below the map
