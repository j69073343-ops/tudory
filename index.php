<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Griffon - Cihaz Takip Yönetim Yazılımı</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
  html { scroll-behavior: smooth; }

  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

  body {
      font-family: 'Inter', sans-serif;
  }

  .gradient-bg {
      background: linear-gradient(90deg, #E53E3E 0%, #DD6B20 100%);
  }

  .feature-card {
      transition: all 0.3s ease;
  }

  .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  }

  .nav-link {
      position: relative;
  }

  .nav-link:after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: -2px;
      left: 0;
      background-color: #4F46E5;
      transition: width 0.3s ease;
  }

  .nav-link:hover:after {
      width: 100%;
  }

  .mobile-menu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out;
  }

  .mobile-menu.open {
      max-height: 500px;
  }
</style>

<header class="bg-gray-600 shadow-md">
  <div class="container mx-auto px-4 py-3 flex items-center justify-between">
    <!-- Sol Logo -->
    <a href="#" class="flex items-center space-x-3">
      <img src="assets/logo.png" alt="Griffon Logo" class="h-16 w-auto">
    </a>

    <!-- Sağ Menü -->
    <div class="md:hidden">
      <button id="menu-toggle" class="text-gray-800 focus:outline-none">
        <i class="fas fa-bars text-2xl"></i>
      </button>
    </div>

    <!-- Masaüstü Menü -->
    <nav class="hidden md:flex space-x-6 text-gray-800 font-medium">
      <a href="#griffon-neler" class="nav-link hover:text-red-600">Özellikler</a>
      <a href="#" class="nav-link hover:text-red-600">Destek</a>
      <a href="https://wa.me/905011891645" target="_blank" class="px-5 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">Ücretsiz İndir</a>
    </nav>
  </div>
</header>


    <!-- Hero Section -->
    <section class="bg-gray-900 text-white py-12 md:py-20">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-8 md:mb-0">
                    <h1 class="text-3xl md:text-4xl font-bold mb-4">Akıllı Telefonlar İçin Gelişmiş Takip Programı</h1>
                    <p class="text-lg mb-6">Griffon ile sevdiklerinizin veya şüphelendiklerinizin telefon aktivitelerini takip edin. Arama kayıtları, mesajlar, konum ve daha fazlası.</p>
                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                        <a href="#griffon-neler" class="px-6 py-2 bg-red-600 text-white rounded-md font-medium hover:bg-red-700 text-center">DAHA FAZLA BİLGİ AL</a>
                        
                    </div>
                </div>
                <div class="md:w-1/2 flex justify-center">
<img src="assets/logo.png" alt="logo" class="h-24">

                </div>
            </div>
        </div>
    </section>

    <!-- Clients Section -->
    <section class="py-12 bg-gray-50">
        <div class="container mx-auto px-4">
            <h2 class="text-center text-2xl font-semibold text-gray-700 mb-10">On Binlerce kullanıcı Griffon'u Tercih Ediyor</h2>
            <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16">
                
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16 bg-white" id="griffon-neler">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Griffon Yazılımı ile Neler Yapabilirsiniz?</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Telefon takip yazılımımızın güçlü özelliklerini keşfedin.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Feature 1 -->
                <div class="feature-card bg-gray-50 p-6 rounded-lg">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-phone-alt text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Arama Kayıtları</h3>
                    <p class="text-gray-600">Tüm gelen/giden aramaları ve temasları takip edin.</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="feature-card bg-gray-50 p-6 rounded-lg">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-sms text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">SMS/WhatsApp Takibi</h3>
                    <p class="text-gray-600">Gönderilen ve alınan tüm mesajları görüntüleyin.</p>
                </div>
                
                <!-- Feature 3 -->
                <div class="feature-card bg-gray-50 p-6 rounded-lg">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-map-marker-alt text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Konum Takibi</h3>
                    <p class="text-gray-600">Gerçek zamanlı konum geçmişini görüntüleyin.</p>
                </div>
                
                <!-- Feature 4 -->
                <div class="feature-card bg-gray-50 p-6 rounded-lg">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-camera text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Ekran Görüntüsü</h3>
                    <p class="text-gray-600">Canlı bir şekilde hedeflediğiniz kişinin ekranını izleyinf.</p>
                </div>
                
                <!-- Feature 5 -->
                <div class="feature-card bg-gray-50 p-6 rounded-lg">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-keyboard text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Klavye Kaydı</h3>
                    <p class="text-gray-600">Yazılan tüm metinleri ve şifreleri kaydedin.</p>
                </div>
                
                <!-- Feature 6 -->
                <div class="feature-card bg-gray-50 p-6 rounded-lg">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-microphone text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Ses Kaydı</h3>
                    <p class="text-gray-600">Ortam seslerini ve konuşmaları kaydedin.</p>
					
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Griffon Yazılımı Nasıl Çalışır?</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Griffon'u kullanmak çok kolay.</p>
            </div>
            
            <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                <!-- Step 1 -->
                <div class="text-center max-w-xs">
                    <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 text-2xl font-bold">1</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Uygulama İçin Üyelik Al</h3>
                    <p class="text-gray-600">İlk adım uygulamayı kullanmak için üyelik almaktır.</p>
                </div>
                
                <!-- Arrow -->
                <div class="hidden md:block">
                    <i class="fas fa-arrow-right text-gray-400 text-3xl"></i>
                </div>
                
                <!-- Step 2 -->
                <div class="text-center max-w-xs">
                    <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 text-2xl font-bold">2</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Takip Edeceğin Kişinin Numarası</h3>
                    <p class="text-gray-600">Takip etmek istediğin kişinin telefon numarasını uygulamaya girersin.</p>
                </div>
                
                <!-- Arrow -->
                <div class="hidden md:block">
                    <i class="fas fa-arrow-right text-gray-400 text-3xl"></i>
                </div>
                
                <!-- Step 3 -->
                <div class="text-center max-w-xs">
                    <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 text-2xl font-bold">3</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Takip Etmeye Başla</h3>
                    <p class="text-gray-600">Ardından takip etmek istediğin kişiyi uygulamayla birebir takip etmiş olursun.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Fiyatlandırma</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">İhtiyaçlarınıza uygun paketlerimizden birini seçin.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <!-- Basic Plan -->
                <div class="border border-gray-200 rounded-xl p-8 hover:border-indigo-500 transition">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Bronz Paket</h3>
                    <p class="text-gray-600 mb-6">.</p>
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-gray-800">₺3.375</span>
                        <span class="text-gray-600">/Aylık</span>
                    </div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>1 kişiye kadar takip edebilme</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Android ve İOS Cihazlar İçin</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Tüm Özelliklere Tam Erişim</span>
                        </li>
                    </ul>
                    <a target="_blank" rel="noopener noreferrer" href="https://wa.me/905011891645" class="block w-full py-3 px-6 bg-gray-100 text-gray-800 rounded-lg text-center font-medium hover:bg-gray-200">Paketi Seç</a>
                </div>
                
                <!-- Pro Plan -->
                <div class="border-2 border-indigo-500 rounded-xl p-8 relative">
                    <div class="absolute top-0 right-0 bg-indigo-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg rounded-tr-lg">POPÜLER</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Elmas Paket</h3>
                    <p class="text-gray-600 mb-6">Kullanıcıların En Çok Tercih Ettiği</p>
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-gray-800">₺7.455</span>
                        <span class="text-gray-600">/Aylık</span>
                    </div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>5 kişiye kadar takip edebilme</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Takip Edilen Cihazları Yönetebilme</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Android, İOS ve Windows (Bilgisayar), MAC (Bilgisayar) Cihazlar İçin</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Tüm Özelliklere Tam Erişim</span>
                        </li>
                    </ul>
                    <a target="_blank" rel="noopener noreferrer" href="https://wa.me/905011891645" class="block w-full py-3 px-6 bg-indigo-600 text-white rounded-lg text-center font-medium hover:bg-indigo-700">Paketi Seç</a>
                </div>
                
               
                        </li>
                  
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Müşterilerimiz Ne Diyor?</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Griffon'u kullananların deneyimlerini okuyun.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-white p-8 rounded-xl shadow-sm">
                    <div class="flex items-center mb-6">
                        <img src="https://randomuser.me/api/portraits/women/45.jpg" alt="Testimonial" class="w-12 h-12 rounded-full mr-4">
                        <div>
                            <h4 class="font-semibold text-gray-800">Sedef Pekin</h4>
                            <p class="text-gray-600 text-sm">3 Aydır Abone</p>
                        </div>
                    </div>
                    <p class="text-gray-600">"Eşimin beni aldattığından şüpheleniyordum. Ne zaman şüphelerimden bahsetsem, sen kafanda kuruyorsun diyordu. Griffon'u gördüm ve üyelik almak istedim. Sonrasında beni aldattığı kadınla olan konuşmalarını yakaladım. Şükür boşandım ve kurtuldum. Bu yazılımda emeği geçen herkesten Allah razı olsun"</p>
                    <div class="flex mt-4 text-yellow-400">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                
                <!-- Testimonial 2 -->
                <div class="bg-white p-8 rounded-xl shadow-sm">
                    <div class="flex items-center mb-6">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Testimonial" class="w-12 h-12 rounded-full mr-4">
                        <div>
                            <h4 class="font-semibold text-gray-800">Mehmet Ali Uncu</h4>
                            <p class="text-gray-600 text-sm">14 Aydır Abone</p>
                        </div>
                    </div>
                    <p class="text-gray-600">"İşim nedeniyle şehir dışına çok çıkıyorum. Eşime güveniyorum ama dışarıya güvenemiyorum. İçim rahat etsin diye uygulamadan aylık üyelik aldım. Şimdiye kadar bir sıkıntı yok. Sadece bazen ekranı canlı izlerken ufak tefek kasmalar oluyor. Onun dışında uygulamadan memnunum. Her şeyi görebiliyorum. Aldığı parayı hakediyor."</p>
                    <div class="flex mt-4 text-yellow-400">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                </div>
                
                <!-- Testimonial 3 -->
                <div class="bg-white p-8 rounded-xl shadow-sm">
                    <div class="flex items-center mb-6">
                        <img src="https://randomuser.me/api/portraits/women/19.jpg" alt="Testimonial" class="w-12 h-12 rounded-full mr-4">
                        <div>
                            <h4 class="font-semibold text-gray-800">Büşra Nur Şahin</h4>
                            <p class="text-gray-600 text-sm">7 Aydır Abone</p>
                        </div>
                    </div>
                    <p class="text-gray-600">"İşyeri arkadaşlarım kendi oluşturdukları Whatsapp grubunda hakkımda konuşuyor, iftira atıyorlardı. Hiçbir şekilde kanıtlayamıyordum. İlahi Adalet, bu uygulama karşıma çıktı. Aldım, konuşmalara erişip iftira atıldığını kanıtladım. İyiki böyle bir uygulama var. Çok teşekkürler."</p>
                    <div class="flex mt-4 text-yellow-400">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-red-600 text-white py-12">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-4">Griffon'u Deneyin!</h2>
            <p class="text-xl mb-6 max-w-2xl mx-auto">Hizmeti beğenmediğiniz takdirde ücretiniz aynı gün iade!.</p>
            <a target="_blank" rel="noopener noreferrer" href="https://wa.me/905011891645" class="inline-block px-8 py-3 bg-white text-red-600 rounded-md font-bold hover:bg-gray-100">ÜCRETSİZ İNDİR</a>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center
</html>
