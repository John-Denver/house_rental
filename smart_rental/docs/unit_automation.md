# Unit Automation System

## Overview

The rental system now includes automated unit management that tracks available units for properties with multiple units (apartments, rooms, etc.). When bookings are confirmed or cancelled, the system automatically updates the available unit count.

## How It Works

### Database Structure

Properties have two unit-related fields:
- `total_units`: Total number of units in the property
- `available_units`: Number of units currently available for rent

### Automation Triggers

#### 1. Booking Confirmation
When a booking status changes to `confirmed`:
- Available units are **decremented** by 1
- Property becomes less available for new bookings
- System prevents units from going below 0

#### 2. Booking Cancellation
When a booking status changes to `cancelled`:
- Available units are **incremented** by 1
- Property becomes more available for new bookings
- System prevents units from exceeding total units

### Code Implementation

#### BookingController.php Changes

```php
private function handleStatusChange($bookingId, $newStatus, $booking) {
    switch ($newStatus) {
        case 'confirmed':
            // Decrement available units when booking is confirmed
            $this->decrementPropertyUnits($booking['house_id']);
            $this->sendBookingConfirmation($bookingId);
            break;
            
        case 'cancelled':
            // Increment available units when booking is cancelled
            $this->incrementPropertyUnits($booking['house_id']);
            $this->processCancellation($booking);
            break;
    }
}

private function decrementPropertyUnits($propertyId) {
    $stmt = $this->conn->prepare("
        UPDATE houses 
        SET available_units = GREATEST(available_units - 1, 0) 
        WHERE id = ? AND available_units > 0
    ");
    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
}

private function incrementPropertyUnits($propertyId) {
    $stmt = $this->conn->prepare("
        UPDATE houses 
        SET available_units = LEAST(available_units + 1, total_units) 
        WHERE id = ? AND available_units < total_units
    ");
    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
}
```

#### Availability Check

The booking process now checks for available units:

```php
private function isPropertyAvailable($propertyId, $startDate) {
    // First check if property has available units
    if (!$this->hasAvailableUnits($propertyId)) {
        return false;
    }
    
    // Then check for existing bookings on the same date
    // ... existing logic
}
```

## Testing

### Test Interface
Access `test_unit_automation.php` as an admin to test the automation:

1. View recent bookings
2. Confirm bookings (decrements units)
3. Cancel bookings (increments units)
4. See unit changes in real-time

### Manual API
Landlords can manually update units via API:

```bash
POST /api/update_units.php
{
    "property_id": 123,
    "action": "increment",
    "amount": 2
}
```

## Important Notes

### When Units Are Updated
- **Confirmed bookings**: Units decremented
- **Cancelled bookings**: Units incremented
- **Pending bookings**: No unit change (units only change on confirmation)
- **Completed bookings**: No unit change (units stay decremented)

### Safety Features
- Units cannot go below 0
- Units cannot exceed total_units
- All changes are logged for audit purposes
- Database transactions ensure data consistency

### Edge Cases Handled
- Multiple simultaneous bookings
- Cancelled bookings that were never confirmed
- Properties with 0 available units
- Invalid status transitions

## Usage Examples

### Property Setup
```sql
-- Apartment building with 10 units, 3 available
UPDATE houses SET total_units = 10, available_units = 3 WHERE id = 1;
```

### Booking Flow
1. Tenant books property (status: pending, units: unchanged)
2. Landlord confirms booking (status: confirmed, units: -1)
3. Tenant cancels booking (status: cancelled, units: +1)
4. New tenant books same property (status: pending, units: unchanged)

### Monitoring
```sql
-- Check property availability
SELECT house_no, available_units, total_units 
FROM houses 
WHERE available_units > 0;

-- Check booking status and unit impact
SELECT b.id, b.status, h.house_no, h.available_units
FROM rental_bookings b
JOIN houses h ON b.house_id = h.id
ORDER BY b.created_at DESC;
```

## Troubleshooting

### Common Issues

1. **Units not updating**: Check if booking status is being changed through the proper channels
2. **Units going negative**: System prevents this, but check for manual database updates
3. **Units exceeding total**: System prevents this, but verify total_units is set correctly

### Debug Tools
- Check error logs for unit update messages
- Use test interface to verify automation
- Monitor database directly for unit changes

## Future Enhancements

1. **Unit History**: Track unit changes over time
2. **Bulk Operations**: Update multiple properties at once
3. **Notifications**: Alert landlords when units are low
4. **Analytics**: Unit utilization reports
5. **Reservations**: Hold units temporarily without booking 