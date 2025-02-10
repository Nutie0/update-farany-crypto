using Microsoft.EntityFrameworkCore;
using Microsoft.OpenApi.Models;
using Microsoft.AspNetCore.Identity;
using Microsoft.IdentityModel.Tokens;
using Microsoft.Extensions.Options;
using UserApi.Models;
using UserApi.Services;
using Microsoft.AspNetCore.Authentication.JwtBearer;
using Microsoft.Extensions.Caching.Memory;  // Ajout pour IMemoryCache
using System.Text;

var builder = WebApplication.CreateBuilder(args);

// Configuration de la base de données
builder.Services.AddDbContext<UserApi.Data.ApplicationDbContext>(options =>
    options.UseNpgsql(builder.Configuration.GetConnectionString("DefaultConnection")));

// Enregistrement du service PasswordHasher
builder.Services.AddScoped<IPasswordHasher<Utilisateur>, PasswordHasher<Utilisateur>>();

// Enregistrement des autres services
builder.Services.AddScoped<PasswordHasher>();
builder.Services.AddTransient<EmailService>();

// Enregistrement du service IMemoryCache
builder.Services.AddMemoryCache();  // Ajout du cache en mémoire

// Configuration du JWT
var jwtSettings = builder.Configuration.GetSection("JwtSettings").Get<JwtSettings>();

builder.Services.AddAuthentication(JwtBearerDefaults.AuthenticationScheme)
    .AddJwtBearer(options =>
    {
        options.RequireHttpsMetadata = false;
        options.SaveToken = true;
        options.TokenValidationParameters = new TokenValidationParameters
        {
            ValidateIssuer = true,
            ValidateAudience = true,
            ValidateLifetime = true,  // Validation de la durée de vie du token
            ValidIssuer = jwtSettings.Issuer,
            ValidAudience = jwtSettings.Audience,
            IssuerSigningKey = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(jwtSettings.SecretKey)),
            ClockSkew = TimeSpan.Zero  // Pas de délai toléré pour l'expiration du token
        };
    });

// Ajout des contrôleurs
builder.Services.AddControllers();

// Configuration CORS
builder.Services.AddCors(options =>
{
    options.AddDefaultPolicy(policy =>
    {
        policy.WithOrigins("http://localhost:8000")
              .AllowAnyMethod()
              .AllowAnyHeader()
              .AllowCredentials();
    });
});

// Configuration de Swagger
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen(c =>
{
    c.SwaggerDoc("v1", new OpenApiInfo { Title = "My API", Version = "v1" });
});

// Configuration du journal
builder.Logging.AddConsole();

var app = builder.Build();

// Configuration du port 5280
app.Urls.Add("http://localhost:5280");

// Utiliser Swagger pour la documentation de l'API en mode développement
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI(c => c.SwaggerEndpoint("/swagger/v1/swagger.json", "My API v1"));
}

// Utilisation du CORS avant le routing et l'authentification
app.UseCors();

// Middleware pour l'authentification et l'autorisation
app.UseAuthentication();  // Permet l'authentification avec le JWT
app.UseAuthorization();   // Permet d'utiliser les autorisations définies

// Middleware pour la redirection HTTPS
app.UseHttpsRedirection();

// Mapper les contrôleurs
app.MapControllers();

app.Run();
