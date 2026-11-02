import com.android.build.api.variant.LibraryAndroidComponentsExtension

allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
subprojects {
    project.evaluationDependsOn(":app")
}

// Raise compileSdk of third-party Flutter plugin modules (e.g. file_picker) to at least 36,
// as required by flutter_plugin_android_lifecycle.
//
// Why finalizeDsl: a plugin's own `android { compileSdk = ... }` block runs AFTER
// plugins.withId(...) fires, so setting the value there is silently overwritten
// (the plugin stays on android-34). afterEvaluate is too late: AGP has already read
// compileSdk ("It is too late to set compileSdk"). finalizeDsl runs after every
// build script `android {}` block has been parsed and before AGP consumes the values.
// Only ever raises the value; never lowers it.
subprojects {
    plugins.withId("com.android.library") {
        extensions.configure<LibraryAndroidComponentsExtension> {
            finalizeDsl { libraryExtension ->
                val current = libraryExtension.compileSdk ?: 0
                if (current < 36) {
                    libraryExtension.compileSdk = 36
                }
            }
        }
    }
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
