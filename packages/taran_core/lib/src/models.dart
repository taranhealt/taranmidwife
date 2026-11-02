class UserInfo {
  const UserInfo({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.verificationStatus,
  });

  factory UserInfo.fromJson(Map<String, dynamic> j) => UserInfo(
        id: (j['id'] as num).toInt(),
        name: '${j['name'] ?? ''}',
        email: '${j['email'] ?? ''}',
        role: '${j['role'] ?? ''}',
        verificationStatus: j['verification_status'] as String?,
      );

  final int id;
  final String name;
  final String email;
  final String role;
  final String? verificationStatus;
}

class ServiceItem {
  const ServiceItem({
    required this.id,
    required this.name,
    required this.priceAmount,
    this.description,
    this.limitations,
  });

  factory ServiceItem.fromJson(Map<String, dynamic> j) => ServiceItem(
        id: j['id'] as String,
        name: j['name'] as String,
        priceAmount: (j['price_amount'] as num).toInt(),
        description: j['description'] as String?,
        limitations: j['limitations'] as String?,
      );

  final String id;
  final String name;
  final int priceAmount;
  final String? description;
  final String? limitations;
}

class AreaItem {
  const AreaItem({required this.id, required this.name});

  factory AreaItem.fromJson(Map<String, dynamic> j) =>
      AreaItem(id: j['id'] as String, name: j['name'] as String);

  final String id;
  final String name;
}

class BookingItem {
  const BookingItem({
    required this.id,
    required this.code,
    required this.status,
    required this.scheduledStart,
    required this.priceAmount,
    this.service,
    this.area,
    this.addressLine,
    this.landmark,
    this.contactPhone,
    this.paymentStatus = 'UNPAID',
  });

  factory BookingItem.fromJson(Map<String, dynamic> j) => BookingItem(
        id: j['id'] as String,
        code: j['code'] as String,
        status: j['status'] as String,
        scheduledStart: DateTime.parse(j['scheduled_start'] as String),
        priceAmount: (j['price_amount'] as num).toInt(),
        service: j['service'] as String?,
        area: j['area'] as String?,
        addressLine: j['address_line'] as String?,
        landmark: j['landmark'] as String?,
        contactPhone: j['contact_phone'] as String?,
        paymentStatus: j['payment_status'] as String? ?? 'UNPAID',
      );

  final String id;
  final String code;
  final String status;
  final DateTime scheduledStart;
  final int priceAmount;
  final String? service;
  final String? area;
  final String? addressLine;
  final String? landmark;
  final String? contactPhone;
  final String paymentStatus;
}
