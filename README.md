# Pagopar & AEX Integration for OpenCart 4

[English](#english) | [Español](#español) | [Português](#português)

---

## English

This module integrates the **Pagopar** payment gateway and **AEX** shipping service into OpenCart 4.

### Features

- Complete Pagopar checkout flow integration.
- Dynamic city and zone lookup via AEX API with interactive search.
- Dual language support (Spanish & English `en-gb`).
- Cron job synchronization for order status updates.

### Requirements

- OpenCart 4.x
- PHP 8.1+
- Pagopar Merchant Credentials (Private Token & Public Token)

### Installation

1. Download or clone this repository.
2. Copy the contents of the `upload/` folder into your OpenCart root directory.
3. Go to **Admin > Extensions > Extensions > Payments**, find **Pagopar** and click **Install**.
4. Configure your Private Token, Public Token, and Order Statuses.

### Future Features
- [ ] **Customer Registration Enhancement:** Integrate AEX dynamic city and zone lookup directly into the customer registration form (not just checkout).
- [ ] **Custom Fields Mapping:** Automatically link AEX location data to OpenCart's native custom fields or address tables during registration.


### License

MIT License

---

## Español

Este módulo integra la pasarela de pago **Pagopar** y el servicio de envío **AEX** en OpenCart 4.

### Características

- Integración completa del flujo de pago de Pagopar.
- Búsqueda dinámica de ciudades y zonas a través de la API de AEX con búsqueda interactiva.
- Soporte para múltiples idiomas (Español e Inglés `en-gb`).
- Sincronización mediante Cron Job para actualizar el estado de los pedidos.

### Requisitos

- OpenCart 4.x
- PHP 8.1+
- Credenciales de Comercio de Pagopar (Token Privado y Token Público)

### Instalación

1. Descarga o clona este repositorio.
2. Copia el contenido de la carpeta `upload/` en el directorio raíz de tu OpenCart.
3. Ve a **Admin > Extensiones > Extensiones > Pagos**, busca **Pagopar** y haz clic en **Instalar**.
4. Configura tu Token Privado, Token Público y los Estados del Pedido.

### Próximas Actualizaciones
- [ ] **Mejora en el Registro de Usuarios:** Integrar la búsqueda dinámica de ciudades y zonas de AEX directamente en el formulario de registro de clientes (no solo en el checkout).
- [ ] **Mapeo de Campos Personalizados:** Vincular automáticamente los datos de ubicación de AEX a los campos personalizados nativos o tablas de direcciones de OpenCart durante el registro.

### Licencia

Licencia MIT

---

## Português

Este módulo integra o gateway de pagamento **Pagopar** e o serviço de frete **AEX** ao OpenCart 4.

### Funcionalidades

- Integração completa do fluxo de checkout do Pagopar.
- Busca dinâmica de cidades e zonas via API da AEX com pesquisa interativa.
- Suporte a múltiplos idiomas (Espanhol e Inglês `en-gb`).
- Sincronização via Cron Job para atualização do status dos pedidos.

### Requisitos

- OpenCart 4.x
- PHP 8.1+
- Credenciais de Lojista Pagopar (Token Privado e Token Público)

### Instalação

1. Baixe ou clone este repositório.
2. Copie o conteúdo da pasta `upload/` para o diretório raiz do seu OpenCart.
3. Acesse **Admin > Extensões > Extensões > Pagamentos**, localize o **Pagopar** e clique em **Instalar**.
4. Configure seu Token Privado, Token Público e os Status do Pedido.

### Próximas Atualizações
- [ ] **Melhoria no Registro de Usuários:** Integrar a busca dinâmica de cidades e zonas da AEX diretamente no formulário de cadastro de clientes (e não apenas no checkout).
- [ ] **Mapeamento de Campos Customizados:** Vincular automaticamente os dados de localização da AEX aos campos personalizados nativos ou tabelas de endereços do OpenCart durante o registro.

### Licença

Licença MIT
