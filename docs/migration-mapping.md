# Fachliches Mapping – Contao → DICOMA (vorläufig)

| Contao DCA | DICOMA im geprüften Archiv | Bemerkung |
|---|---|---|
| `tl_dc_equipment` | `EquipmentItem` | Felder und Semantik noch nicht 1:1 verifiziert |
| `tl_dc_tanks` | `Tank`, `MemberTank` | Verein und Mitglieder getrennt |
| `tl_dc_regulators` | `Regulator`, `EquipmentRegulatorDetails` | unterschiedliche Modelle; Detailabgleich ausstehend |
| `tl_dc_check_proposal` | `TankCheck` | Angebot und Termin fachlich nicht identisch |
| `tl_dc_check_articles` | `TankCheckArticle` | Preispositionen |
| `tl_dc_check_booking` | `MemberTankCheckBooking` | Statusmodell gesondert prüfen |
| `tl_dc_check_order` | `MemberTankCheckBookingItem` | nur fachliche Annäherung |

**Keine automatische Datenmigration** aus diesem Mapping ableiten.
