# NeZnam računi i fiskalizacija

WooCommerce plugin za izdavanje i fiskalizaciju računa direktno u WordPressu za hrvatsko tržište

## Description

Ovo je plugin za izdavanje fiskaliziranih računa direktno iz WooCommerca na hrvatskom tržištu, bez potrebe za rješenima treće strane.

Plugin prilikom završetka narudžbe, kreira novi račun s odgovarajućim sekvencijalnim brojem računa i izgledom broja računa kako je definirano
u postavkama. Potom ga fiskalizira pozivom na servis Porezne uprave. Sprema JIR/ZKI.

Plugin dodaje informacije o računu u Completed email.

Autor dodatka se odriče bilo kakve odgovornosti za potencijalnu štetu uzrokovanu korištenjem ovog dodatka. Ovaj dodatak je u beta verziji i molim da ga tako i koristite.
Ukoliko imate sumnje da plugin ne radi dobro, prijavite [ovdje](https://github.com/ne-znam/woocommerce-racuni-fiskalizacija/issues)

## Installation

1. Prenesite zip kroz admin sučelje
1. Aktivirajte dodatak kroz Dodaci sučelje u WordPressu
1. Namjestite postavke u Postavkama

## Pitanja

### Moram li imati FINA certifikat?

Da, i morate ga namjestiti u postavkama dodatka

### Plugin odbija prihvatiti moj certifikat i lozinku =

Ako koristite noviju verziju PHPa (v8) i OpenSSLa (v3) i imate stariji certifikat, onda je moguće da plugin neće prihvatiti vaš certifikat.
Namjestite ove postavke u /etc/ssl/openssl.cnf

```
[openssl_init]
providers = provider_sect
[provider_sect]
default = default_sect
legacy = legacy_sect
[default_sect]
activate = 1
[legacy_sect]
activate = 1
```

### Kome se mogu obratiti za pomoć?  =

Autor ovog dodatka nudi usluge savjetovanja i namještanja ovog dodatka.

### Mogu li tužiti autora plugina za dobivenu kaznu od Porezne uprave? =

Ne, autor se odriče bilo kakve odgovornosti za neispravno korištenje i namještanje ovog dodatka. Vlasnik webshopa i osoba koja ga je namjestila
su same odgovorne za korištenje dodatka koje je rezultiralo kaznom.

## Webshopovi koji koriste ovaj dodatak

- [Maritimo recycling](https://maritimo-recycling.com/)
