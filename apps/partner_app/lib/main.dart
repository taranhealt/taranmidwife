import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

const apiUrl = String.fromEnvironment('API_BASE_URL', defaultValue: '');

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final tokens = SecureTokenStore();
  final api = ApiClient(baseUrl: apiUrl, tokens: tokens);
  runApp(MultiProvider(
    providers: [
      Provider<ApiClient>.value(value: api),
      ChangeNotifierProvider(create: (_) => AuthController(api, tokens)),
    ],
    child: const MitraApp(),
  ));
}

class MitraApp extends StatelessWidget {
  const MitraApp({super.key});
  @override
  Widget build(BuildContext context) {
    final u = Uri.tryParse(apiUrl);
    final valid = u != null && u.scheme == 'https' && u.host.isNotEmpty &&
        u.userInfo.isEmpty && u.query.isEmpty && u.fragment.isEmpty;
    return MaterialApp(
      title: 'TaranMidwife Mitra',
      debugShowCheckedModeBanner: false,
      theme: taranTheme(),
      home: valid ? const AuthGate() : const MessagePage(
        title: 'Konfigurasi belum tersedia',
        message: 'Build mitra membutuhkan API_BASE_URL HTTPS yang valid.',
      ),
    );
  }
}

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});
  @override
  State<AuthGate> createState() => _AuthGateState();
}
class _AuthGateState extends State<AuthGate> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<AuthController>().bootstrap();
    });
  }
  @override
  Widget build(BuildContext context) {
    final a = context.watch<AuthController>();
    if (a.status == AuthStatus.unknown) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (a.status == AuthStatus.signedOut) return const LoginPage();
    if (a.user?.role != 'partner') {
      return MessagePage(title: 'Akun bukan mitra',
        message: 'Aplikasi ini hanya untuk akun mitra/bidan terdaftar.',
        action: FilledButton(
          onPressed: () => context.read<AuthController>().logout(),
          child: const Text('Keluar')));
    }
    return const MitraHome();
  }
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
  @override
  State<LoginPage> createState() => _LoginPageState();
}
class _LoginPageState extends State<LoginPage> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool busy = false, hidden = true;
  String? error;
  @override
  void dispose() { email.dispose(); password.dispose(); super.dispose(); }
  Future<void> submit() async {
    if (!email.text.contains('@') || password.text.isEmpty) {
      setState(() => error = 'Masukkan email dan kata sandi.');
      return;
    }
    setState(() { busy = true; error = null; });
    try {
      await context.read<AuthController>().login(
        email: email.text.trim(), password: password.text);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.displayMessage);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(child: ListView(padding: const EdgeInsets.all(24), children: [
      const SizedBox(height: 48),
      Text('TaranMidwife Mitra', style: Theme.of(context).textTheme.headlineMedium),
      const Text('Kelola layanan dan kunjungan kebidanan Anda.'),
      const SizedBox(height: 32),
      TextField(controller: email, keyboardType: TextInputType.emailAddress,
        decoration: const InputDecoration(labelText: 'Email')),
      const SizedBox(height: 12),
      TextField(controller: password, obscureText: hidden,
        decoration: InputDecoration(labelText: 'Kata sandi',
          suffixIcon: IconButton(
            onPressed: () => setState(() => hidden = !hidden),
            icon: Icon(hidden ? Icons.visibility : Icons.visibility_off)))),
      if (error != null) Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
      const SizedBox(height: 20),
      FilledButton(onPressed: busy ? null : submit,
        child: busy ? const CircularProgressIndicator() : const Text('Masuk')),
      const SizedBox(height: 12),
      const Text('Akun mitra dibuat melalui proses pendaftaran dan verifikasi resmi.'),
    ])),
  );
}

class MitraHome extends StatefulWidget {
  const MitraHome({super.key});
  @override
  State<MitraHome> createState() => _MitraHomeState();
}
class _MitraHomeState extends State<MitraHome> {
  int tab = 0;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('TaranMidwife Mitra')),
    body: IndexedStack(index: tab, children: const [
      BookingsPage(), ProfilePage(), OfferingsPage(),
    ]),
    bottomNavigationBar: NavigationBar(selectedIndex: tab,
      onDestinationSelected: (v) => setState(() => tab = v),
      destinations: const [
        NavigationDestination(icon: Icon(Icons.calendar_month), label: 'Pesanan'),
        NavigationDestination(icon: Icon(Icons.verified_user), label: 'Profil'),
        NavigationDestination(icon: Icon(Icons.tune), label: 'Layanan'),
      ]),
  );
}

class BookingsPage extends StatefulWidget {
  const BookingsPage({super.key});
  @override
  State<BookingsPage> createState() => _BookingsPageState();
}
class _BookingsPageState extends State<BookingsPage> {
  List<Map<String, dynamic>> rows = [];
  bool loading = true;
  String? error;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async {
    try {
      final r = await context.read<ApiClient>().send('GET', '/bookings',
        query: {'per_page': '50'});
      final list = (r['data'] as List? ?? const [])
        .whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      if (mounted) setState(() { rows = list; loading = false; error = null; });
    } on ApiException catch (e) {
      if (mounted) setState(() { error = e.displayMessage; loading = false; });
    }
  }
  Future<void> act(String id, String action) async {
    try {
      await context.read<ApiClient>().send('POST', '/midwife/bookings/' + id + '/' + action);
      await load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.displayMessage)));
    }
  }
  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) return RetryPage(message: error!, retry: () {
      setState(() => loading = true); load();
    });
    return RefreshIndicator(onRefresh: load, child: rows.isEmpty
      ? ListView(children: const [Padding(padding: EdgeInsets.all(32),
          child: Center(child: Text('Belum ada pesanan yang ditugaskan.')))])
      : ListView(padding: const EdgeInsets.all(12), children: rows.map((b) {
        final id = (b['id'] ?? '').toString();
        final status = (b['status'] ?? '-').toString();
        final actions = <Widget>[];
        if (status == 'OFFERED') {
          actions.add(OutlinedButton(onPressed: () => act(id, 'decline'), child: const Text('Tolak')));
          actions.add(FilledButton(onPressed: () => act(id, 'accept'), child: const Text('Terima')));
        } else if (status == 'CONFIRMED') {
          actions.add(FilledButton(onPressed: () => act(id, 'start'), child: const Text('Mulai kunjungan')));
        } else if (status == 'IN_PROGRESS') {
          actions.add(FilledButton(onPressed: () => act(id, 'complete'), child: const Text('Selesaikan')));
        }
        return Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(
          crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text((b['service'] ?? 'Layanan').toString(),
              style: Theme.of(context).textTheme.titleMedium),
            Text('Kode: ' + (b['code'] ?? id).toString()),
            Text('Jadwal: ' + (b['scheduled_start'] ?? '-').toString()),
            Text('Status: ' + status),
            if (actions.isNotEmpty) Wrap(spacing: 8, children: actions),
          ])));
      }).toList()));
  }
}

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});
  @override State<ProfilePage> createState() => _ProfilePageState();
}
class _ProfilePageState extends State<ProfilePage> {
  Map<String, dynamic>? data; bool loading = true, saving = false; String? error;
  final nik = TextEditingController(), sip = TextEditingController(), issuer = TextEditingController(), expiry = TextEditingController(), area = TextEditingController();
  @override void initState() { super.initState(); load(); }
  @override void dispose() { nik.dispose(); sip.dispose(); issuer.dispose(); expiry.dispose(); area.dispose(); super.dispose(); }
  bool get editable => ['DRAFT','NEEDS_CORRECTION','EXPIRED'].contains(data?['verification_status']);
  Future<void> load() async { try { final r = await context.read<ApiClient>().send('GET','/midwife/profile'); if (!mounted) return; final p = Map<String,dynamic>.from(r['data'] as Map); setState(() { data=p; loading=false; error=null; nik.clear(); sip.text=(p['sip_number']??'').toString(); issuer.text=(p['sip_issuer']??'').toString(); expiry.text=(p['sip_expires_on']??'').toString(); area.text=(p['practice_area']??'').toString(); }); } on ApiException catch(e) { if(mounted) setState(()=>{error=e.displayMessage, loading=false}); } }
  Future<void> saveProfile() async { final n=nik.text.trim(); if(n.isNotEmpty && !RegExp(r'^\d{16}$').hasMatch(n)){ ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('NIK harus 16 digit.'))); return; } setState(()=>saving=true); try { final b=<String,dynamic>{'sip_number':sip.text.trim(),'sip_issuer':issuer.text.trim(),'sip_expires_on':expiry.text.trim(),'practice_area':area.text.trim()}; if(n.isNotEmpty)b['nik']=n; await context.read<ApiClient>().send('PUT','/midwife/profile',body:b); await load(); } on ApiException catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.displayMessage)));} finally{if(mounted)setState(()=>saving=false);} }
  Future<void> upload(String type) async { final r=await FilePicker.platform.pickFiles(type:FileType.custom,allowedExtensions:type=='FACE_PHOTO'?['jpg','jpeg','png']:['jpg','jpeg','png','pdf']); if(r==null||r.files.single.path==null||!mounted)return; setState(()=>saving=true); try{await context.read<ApiClient>().uploadFile('/midwife/documents',field:'file',file:File(r.files.single.path!),fields:{'type':type}); await load();}on ApiException catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.displayMessage)));}finally{if(mounted)setState(()=>saving=false);} }
  Future<void> submitVerification() async {setState(()=>saving=true);try{await context.read<ApiClient>().send('POST','/midwife/verification/submit');await load();}on ApiException catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.displayMessage)));}finally{if(mounted)setState(()=>saving=false);}}
  @override Widget build(BuildContext context){if(loading)return const Center(child:CircularProgressIndicator());if(error!=null)return RetryPage(message:error!,retry:(){setState(()=>loading=true);load();}); final p=data!; final docs=(p['documents'] as List? ?? const[]).whereType<Map>().toList(); final types=['SIP','STR','IDENTITY','FACE_PHOTO']; final labels={'SIP':'Surat Izin Praktik (SIP)','STR':'Surat Tanda Registrasi (STR)','IDENTITY':'Identitas diri','FACE_PHOTO':'Foto profil'}; final uploaded=docs.map((d)=>d['type']).toSet();
    return RefreshIndicator(onRefresh:load,child:ListView(padding:const EdgeInsets.all(16),children:[
      Text('Status verifikasi: '+(p['verification_status']??'-').toString(),style:Theme.of(context).textTheme.titleMedium),
      if(p['review_reason']!=null) Text('Catatan koreksi: '+p['review_reason'].toString()),
      TextField(controller:nik,keyboardType:TextInputType.number,maxLength:16,enabled:editable&&!saving,decoration:InputDecoration(labelText:'NIK (16 digit)',hintText:(p['nik']??'Belum diisi').toString())),
      TextField(controller:sip,enabled:editable&&!saving,decoration:const InputDecoration(labelText:'Nomor SIP')),
      TextField(controller:issuer,enabled:editable&&!saving,decoration:const InputDecoration(labelText:'Penerbit SIP')),
      TextField(controller:expiry,enabled:editable&&!saving,decoration:const InputDecoration(labelText:'Tanggal berlaku SIP (YYYY-MM-DD)')),
      TextField(controller:area,enabled:editable&&!saving,decoration:const InputDecoration(labelText:'Wilayah praktik')),
      if(editable) FilledButton(onPressed:saving?null:saveProfile,child:const Text('Simpan profil')),
      const Divider(), const Text('Dokumen verifikasi'),
      for(final t in types) ListTile(title:Text(labels[t]!),subtitle:Text(uploaded.contains(t)?'Sudah diunggah':'Belum diunggah'),trailing:editable?IconButton(onPressed:saving?null:()=>upload(t),icon:const Icon(Icons.upload_file)):Icon(uploaded.contains(t)?Icons.check_circle:Icons.lock_outline)),
      if(editable) FilledButton(onPressed:saving?null:submitVerification,child:const Text('Ajukan verifikasi')),
      OutlinedButton(onPressed:()=>context.read<AuthController>().logout(),child:const Text('Keluar')),
    ])); }
}
class OfferingsPage extends StatefulWidget { const OfferingsPage({super.key}); @override State<OfferingsPage> createState()=>_OfferingsPageState(); }
class _OfferingsPageState extends State<OfferingsPage> {
  Map<String,dynamic>? data; List<Map<String,dynamic>> services=[]; List<Map<String,dynamic>> areas=[]; Set<String> selectedServices={}, selectedAreas={}; Map<int,Set<int>> hours={};
  final lat=TextEditingController(), lng=TextEditingController(), radius=TextEditingController(text:'20'); bool loading=true,saving=false; String? error;
  static const slots=[8,10,12,14,16]; static const days=['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
  @override void initState(){super.initState();load();} @override void dispose(){lat.dispose();lng.dispose();radius.dispose();super.dispose();}
  Future<void> load() async {try{final api=context.read<ApiClient>();final rs=await Future.wait([api.send('GET','/midwife/offerings'),api.send('GET','/services',auth:false),api.send('GET','/areas',auth:false)]);if(!mounted)return;final d=Map<String,dynamic>.from(rs[0]['data'] as Map);final sv=(rs[1]['data'] as List? ?? const[]).whereType<Map>().map((e)=>Map<String,dynamic>.from(e)).toList();final ar=(rs[2]['data'] as List? ?? const[]).whereType<Map>().map((e)=>Map<String,dynamic>.from(e)).toList();final hs=<int,Set<int>>{};for(final v in (d['availability'] as List? ?? const[])){if(v is Map){final day=int.tryParse('${v['weekday']}');if(day!=null)hs[day]=(v['hours'] as List? ?? const[]).map((x)=>int.parse('$x')).toSet();}}final sp=d['service_point'] as Map?;setState(()=>{data=d,services=sv,areas=ar,selectedServices=(d['service_ids'] as List? ?? const[]).map((x)=>'$x').toSet(),selectedAreas=(d['area_ids'] as List? ?? const[]).map((x)=>'$x').toSet(),hours=hs,lat.text=(sp?['lat']??'').toString(),lng.text=(sp?['lng']??'').toString(),radius.text=(sp?['radius_km']??20).toString(),loading=false,error=null});}on ApiException catch(e){if(mounted)setState(()=>{error=e.displayMessage,loading=false});}}
  Future<void> save() async {setState(()=>saving=true);try{final availability=<Map<String,dynamic>>[];for(var d=0;d<7;d++){final h=hours[d]??{};if(h.isNotEmpty)availability.add({'weekday':d,'hours':h.toList()..sort()});}final a=lat.text.trim(),b=lng.text.trim();Map<String,dynamic>? point;if(a.isNotEmpty||b.isNotEmpty){final x=double.tryParse(a),y=double.tryParse(b),r=int.tryParse(radius.text);if(x==null||y==null||r==null){throw const ApiException(status:422,message:'Koordinat dan radius titik layanan tidak valid.');}point={'lat':x,'lng':y,'radius_km':r};}await context.read<ApiClient>().send('PUT','/midwife/offerings',body:{'service_ids':selectedServices.toList(),'area_ids':selectedAreas.toList(),'availability':availability,'service_point':point});if(mounted)ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Pengaturan layanan disimpan.')));await load();}on ApiException catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.displayMessage)));}finally{if(mounted)setState(()=>saving=false);}}
  Future<void> toggleAccept(bool v) async {setState(()=>saving=true);try{await context.read<ApiClient>().send('PUT','/midwife/offerings',body:{'accepting_requests':v});await load();}on ApiException catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.displayMessage)));}finally{if(mounted)setState(()=>saving=false);}}
  @override Widget build(BuildContext context){if(loading)return const Center(child:CircularProgressIndicator());if(error!=null)return RetryPage(message:error!,retry:(){setState(()=>loading=true);load();});final d=data!;
    return RefreshIndicator(onRefresh:load,child:ListView(padding:const EdgeInsets.all(16),children:[
      SwitchListTile(contentPadding:EdgeInsets.zero,title:const Text('Menerima permintaan baru'),value:d['accepting_requests']==true,onChanged:saving?null:toggleAccept),
      Text('Pilih layanan',style:Theme.of(context).textTheme.titleMedium),
      for(final s in services) CheckboxListTile(contentPadding:EdgeInsets.zero,value:selectedServices.contains('${s['id']}'),title:Text('${s['name']??'Layanan'}'),onChanged:saving?null:(v)=>setState(()=>v==true?selectedServices.add('${s['id']}'):selectedServices.remove('${s['id']}'))),
      const Divider(),Text('Wilayah layanan',style:Theme.of(context).textTheme.titleMedium),
      for(final a in areas) CheckboxListTile(contentPadding:EdgeInsets.zero,value:selectedAreas.contains('${a['id']}'),title:Text('${a['name']??'Wilayah'}'),onChanged:saving?null:(v)=>setState(()=>v==true?selectedAreas.add('${a['id']}'):selectedAreas.remove('${a['id']}'))),
      const Divider(),Text('Jadwal tersedia (WIB)',style:Theme.of(context).textTheme.titleMedium),
      for(var day=0;day<7;day++) ExpansionTile(title:Text(days[day]),children:[Wrap(spacing:4,children:[for(final slot in slots) FilterChip(label:Text('${slot.toString().padLeft(2,'0')}:00'),selected:(hours[day]??{}).contains(slot),onSelected:saving?null:(v)=>setState(()=>{hours.putIfAbsent(day,()=>{});v?hours[day]!.add(slot):hours[day]!.remove(slot);}))])]),
      const Divider(),Text('Titik layanan',style:Theme.of(context).textTheme.titleMedium),
      TextField(controller:lat,keyboardType:const TextInputType.numberWithOptions(decimal:true,signed:true),decoration:const InputDecoration(labelText:'Latitude')),
      TextField(controller:lng,keyboardType:const TextInputType.numberWithOptions(decimal:true,signed:true),decoration:const InputDecoration(labelText:'Longitude')),
      TextField(controller:radius,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'Radius layanan (km)')),
      const SizedBox(height:16),FilledButton(onPressed:saving?null:save,child:saving?const CircularProgressIndicator():const Text('Simpan pengaturan')),
    ]));}
}
class RetryPage extends StatelessWidget {
  const RetryPage({super.key, required this.message, required this.retry});
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(child: Padding(
    padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
      Text(message, textAlign: TextAlign.center),
      const SizedBox(height: 12), FilledButton(onPressed: retry, child: const Text('Coba lagi')),
    ])));
}

class MessagePage extends StatelessWidget {
  const MessagePage({super.key, required this.title, required this.message, this.action});
  final String title, message;
  final Widget? action;
  @override
  Widget build(BuildContext context) => Scaffold(body: Center(child: Padding(
    padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
      Text(title, style: Theme.of(context).textTheme.titleLarge),
      const SizedBox(height: 12), Text(message, textAlign: TextAlign.center),
      if (action != null) action!,
    ]))));
}
